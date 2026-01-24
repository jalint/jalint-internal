<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLhpDocumentRequest;
use App\Models\LhpDocument;
use App\Models\LhpDocumentParameter;
use App\Models\LhpReview;
use App\Models\LhpStep;
use App\Models\Offer;
use App\Models\OfferSample;
use App\Queries\LhpVisibility;
use App\Services\LhpDisplayStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LhpDocumentController extends Controller
{
    public function summary(Request $request)
    {
        $role = auth()->user()
        ->roles()
        ->whereIn('name', [
            'admin_login',
            'analis',
            'penyelia_lab',
            'admin_input_lhp',
            'manager_teknis',
            'admin_premlim',
        ])
        ->value('name');

        $base = LhpVisibility::forRole($role)->with('currentReview');

        if (!$request->filled('start_date') && !$request->filled('end_date')) {
            $base->whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]);
        }

        if ($request->filled('start_date')) {
            $base->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $base->whereDate('created_at', '<=', $request->end_date);
        }

        $summary = [];
        $summary['all'] = (clone $base)->count();

        switch ($role) {
            /*
            |--------------------------------------------------------------------------
            | ADMIN PENAWARAN
            |--------------------------------------------------------------------------
            */
            case 'admin_login':
                $summary['menunggu_verif_lhp'] = (clone $base)
                    ->where('status', 'draft')
                    ->count();

                $summary['cek_analisis_hasil'] = (clone $base)
                    ->whereIn('status', ['in_review', 'in_analysis', 'revised'])
                    ->count();

                // ✅ Cek Pembayaran Pelanggan (pending ONLY)
                $summary['lhp_disetujui'] = (clone $base)
                 ->where('status', 'validated')
                    ->count();
                break;

            case 'analis':
                $summary['analisa_data'] = (clone $base)->where('status', 'draft')->count();
                $summary['lhp_telah_diisi'] = (clone $base)->whereIn('status', ['in_analysis', 'validated'])->count();
                $summary['revisi_lhp'] = (clone $base)->where('status', 'revised')->count();
                break;

            case 'penyelia_lab':
                $summary['verifikasi_lhp'] = (clone $base)->where('status', 'in_analysis')->count();
                $summary['lhp_terverifikasi'] = (clone $base)->whereIn('status', ['in_review', 'validated'])
                ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')->where('role', 'penyelia_lab')
                )
                ->count();
                $summary['review_revisi'] = (clone $base)->where('status', 'revised')
                ->whereHas('latestRevisedReview', fn ($q) => $q->where('role', 'penyelia_lab')
                )->count();

                break;
            case 'admin_input_lhp':
                $summary['cek_lhp'] = (clone $base)->where('status', 'in_review')
                ->whereHas('currentReview', fn ($q) => $q->where('role', 'admin_input_lhp')
                )
                ->count();

                $summary['hasil_selesai_dicek'] = (clone $base)->whereIn('status', ['in_review', 'validated'])
                ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')->where('role', 'admin_input_lhp')
                )
                ->count();
                $summary['lhp_direvisi'] = (clone $base)->where('status', 'revised')
                ->whereHas('latestRevisedReview', fn ($q) => $q->where('role', 'admin_input_lhp')
                )->count();
                break;
            case 'manager_teknis':
                $summary['validasi_lhp'] = (clone $base)->where('status', 'in_review')
                ->whereHas('currentReview', fn ($q) => $q->where('role', 'manager_teknis')
                )
                ->count();

                $summary['lhp_tervalidasi'] = (clone $base)->whereIn('status', ['in_review', 'validated'])
                ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')->where('role', 'manager_teknis')
                )
                ->count();

                $summary['lhp_direvisi'] = (clone $base)->where('status', 'revised')
                ->whereHas('latestRevisedReview', fn ($q) => $q->where('role', 'manager_teknis')
                )->count();

                break;

            case 'admin_premlim':
                $summary['validasi_lhp'] = (clone $base)->where('status', 'in_review')
                ->whereHas('currentReview', fn ($q) => $q->where('role', 'admin_premlim')
                )
                ->count();

                $summary['lhp_tervalidasi'] = (clone $base)->whereIn('status', ['in_review', 'validated'])
                ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')->where('role', 'admin_premlim')
                )
                ->count();

                $summary['lhp_direvisi'] = (clone $base)->where('status', 'revised')
                ->whereHas('latestRevisedReview', fn ($q) => $q->where('role', 'admin_premlim')
                )->count();

                break;
        }

        return response()->json($summary);
    }

    public function index(Request $request)
    {
        $role = auth()->user()
            ->roles()
            ->whereIn('name', [
                'admin_login',
                'analis',
                'penyelia_lab',
                'admin_input_lhp',
                'manager_teknis',
                'admin_premlim',
            ])
            ->value('name');

        $filter = $request->query('filter'); // key summary
        $search = $request->query('search');

        $query = LhpVisibility::forRole($role)
            ->with([
                'offer:id,offer_number,title,customer_id',
                'offer.customer:id,name',
                'currentReview',
            ])
            ->latest();

        /*
        |--------------------------------------------------------------------------
        | DEFAULT: BULAN BERJALAN
        |--------------------------------------------------------------------------
        */
        if (!$request->filled('start_date') && !$request->filled('end_date')) {
            $query->whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhereHas('offer', fn ($o) => $o->where('offer_number', 'like', "%{$search}%")
                          ->orWhere('title', 'like', "%{$search}%")
                  );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | FILTER PER ROLE (SAMA PERSIS DENGAN SUMMARY)
        |--------------------------------------------------------------------------
        */
        switch ($role) {
            /* ================= ADMIN PENAWARAN ================= */
            case 'admin_login':
                match ($filter) {
                    'menunggu_verif_lhp' => $query->where('status', 'draft'),

                    'cek_analisis_hasil' => $query->whereIn('status', ['in_review', 'in_analysis', 'revised']),

                    'lhp_disetujui' => $query->where('status', 'validated'),

                    default => null
                };
                break;

                /* ================= ANALIS ================= */
            case 'analis':
                match ($filter) {
                    'analisa_data' => $query->where('status', 'draft'),

                    'lhp_telah_diisi' => $query->whereIn('status', ['in_analysis', 'validated']),

                    'revisi_lhp' => $query->where('status', 'revised'),

                    default => null
                };
                break;

                /* ================= PENYELIA ================= */
            case 'penyelia_lab':
                match ($filter) {
                    'verifikasi_lhp' => $query->where('status', 'in_analysis'),

                    'lhp_terverifikasi' => $query->whereIn('status', ['in_review', 'validated'])
                            ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')->where('role', 'penyelia_lab')
                            ),

                    'review_revisi' => $query->where('status', 'revised')
                            ->whereHas('latestRevisedReview', fn ($q) => $q->where('role', 'penyelia_lab')
                            ),

                    default => null
                };
                break;

                /* ================= ADMIN INPUT LHP ================= */
            case 'admin_input_lhp':
                match ($filter) {
                    'cek_lhp' => $query->where('status', 'in_review')
                            ->whereHas('currentReview', fn ($q) => $q->where('role', 'admin_input_lhp')
                            ),

                    'hasil_selesai_dicek' => $query->whereIn('status', ['in_review', 'validated'])
                            ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')->where('role', 'admin_input_lhp')
                            ),

                    'lhp_direvisi' => $query->where('status', 'revised')
                            ->whereHas('latestRevisedReview', fn ($q) => $q->where('role', 'admin_input_lhp')
                            ),

                    default => null
                };
                break;

                /* ================= MANAGER TEKNIS ================= */
            case 'manager_teknis':
                match ($filter) {
                    'validasi_lhp' => $query->where('status', 'in_review')
                            ->whereHas('currentReview', fn ($q) => $q->where('role', 'manager_teknis')
                            ),

                    'lhp_tervalidasi' => $query->whereIn('status', ['in_review', 'validated'])
                            ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')->where('role', 'manager_teknis')
                            ),

                    'lhp_direvisi' => $query->where('status', 'revised')
                            ->whereHas('latestRevisedReview', fn ($q) => $q->where('role', 'manager_teknis')
                            ),

                    default => null
                };
                break;

                /* ================= ADMIN PREMLIM ================= */
            case 'admin_premlim':
                match ($filter) {
                    'validasi_lhp' => $query->where('status', 'in_review')
                            ->whereHas('currentReview', fn ($q) => $q->where('role', 'admin_premlim')
                            ),

                    'lhp_tervalidasi' => $query->whereIn('status', ['in_review', 'validated'])
                            ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')->where('role', 'admin_premlim')
                            ),

                    'lhp_direvisi' => $query->where('status', 'revised')
                            ->whereHas('latestRevisedReview', fn ($q) => $q->where('role', 'admin_premlim')
                            ),

                    default => null
                };
                break;
        }

        $lhps = $query->paginate($request->per_page ?? 15);

        /*
        |--------------------------------------------------------------------------
        | DISPLAY STATUS (SESUI SUMMARY)
        |--------------------------------------------------------------------------
        */
        $lhps->getCollection()->transform(function ($lhp) use ($role) {
            $lhp->display_status = LhpDisplayStatus::resolve($lhp, $role);

            return $lhp;
        });

        return response()->json($lhps);
    }

    public function store(StoreLhpDocumentRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $lhp = LhpDocument::create([
                'offer_id' => $request->offer_id,
                'job_number' => $request->job_number,
                'tanggal_dilaporkan' => $request->tanggal_dilaporkan,
                'status' => 'draft',
            ]);

            $lhp->details()->createMany($request['details']);

            return response()->json([
                'message' => 'LHP berhasil dibuat',
                'data' => $lhp->load('details'),
            ], 201);
        });
    }

    public function show($id)
    {
        $lhpDocument = LhpDocument::query()
               ->with([
                   'details',
                   'details.lhpDocumentParamters',
                   'details.lhpDocumentParamters.offerSampleParameter',
                   'details.lhpDocumentParamters.offerSampleParameter.testParameter:id,test_method_id,name',
                   'details.lhpDocumentParamters.offerSampleParameter.testParameter.testMethod:id,name',
               ])
            ->findOrFail($id);

        $offer = Offer::query()
            ->select([
                'offers.id as offer_id',
                'offers.offer_number',
                'offers.title',
                'offers.location',
                'customers.name as customer_name',
                'cc.name as pic_name',
                'cc.phone as pic_phone',
                'cc.position as pic_position',
            ])
            ->join('customers', 'customers.id', '=', 'offers.customer_id')
            ->leftJoinSub(
                DB::table('customer_contacts')
                    ->select('customer_id', 'name', 'position', 'phone'),
                'cc',
                'cc.customer_id',
                '=',
                'customers.id'
            )
            ->withCount('samples')
            ->where('offers.id', $lhpDocument->offer_id)
            ->firstOrFail();

        if ($lhpDocument->status == 'draft') {
            $samples = OfferSample::query()
                ->where('offer_id', $lhpDocument->offer_id)
                ->with(['parameters.testParameter.testMethod'])
                ->get();
        }

        return response()->json([
            'offer' => [
                'offer_id' => $offer->offer_id,
                'offer_number' => $offer->offer_number,
                'title' => $offer->title,
                'location' => $offer->location,
                'customer_name' => $offer->customer_name,
                'pic_name' => $offer->pic_name,
                'pic_phone' => $offer->pic_phone,
                'pic_position' => $offer->pic_position,
                'total_samples' => $offer->samples_count,
            ],
            'lhp_document' => $lhpDocument,
            'samples' => $samples ?? [],
        ]);
    }

    public function review(Request $request, $id)
    {
        $user = auth()->user();

        $rules = [
            'decision' => 'required|in:approved,revised',
            'note' => 'nullable|string',
        ];

        if ($user->hasRole('admin_input_lhp') && $request->decision != 'revised') {
            $rules = array_merge($rules, [
                'lhp_document_parameters' => 'required|array|min:1',
                'lhp_document_parameters.*.id' => 'required|exists:lhp_document_parameters,id',
                'lhp_document_parameters.*.description_results' => 'required',
            ]);
        }

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated, $id, $user) {
            $lhpDocument = LhpDocument::with(['currentReview'])
            ->lockForUpdate()
            ->findOrFail($id);

            $currentReview = $lhpDocument->currentReview;

            if ($lhpDocument->status == 'in_analysis' && $currentReview->role == 'penyelia_lab') {
                if ($validated['decision'] == 'approved') {
                    $lhpDocument->update(['status' => 'in_review']);

                    $currentReview->update([
                        'decision' => 'approved',
                        'note' => $validated['note'] ?? null,
                        'reviewed_by' => $user->name,
                        'reviewed_at' => now(),
                    ]);

                    $adminInputLhpStepID = 3;

                    LhpReview::create([
                        'lhp_document_id' => $lhpDocument->id,
                        'lhp_step_id' => $adminInputLhpStepID,
                        'role' => 'admin_input_lhp',
                        'decision' => 'pending',
                    ]);

                    return response()->json(['message' => 'Dokumen LHP berhasil diverifikasi']);
                }
            }

            if ($currentReview->role == 'admin_input_lhp') {
                foreach ($validated['lhp_document_parameters'] as $parameter) {
                    $lhpdp = LhpDocumentParameter::where('id', $parameter['id'])
                            ->whereHas('LhpDocumentDetail', function ($q) use ($id) {
                                $q->whereHas('lhp', function ($qq) use ($id) {
                                    $qq->where('id', $id);
                                });
                            })
                            ->firstOrFail();

                    $lhpdp->update([
                        'description_results' => $parameter['description_results'],
                    ]);
                }
            }

            $currentReview->update([
                'decision' => $validated['decision'],
                'note' => $validated['note'] ?? null,
                'reviewed_by' => $user->name,
                'reviewed_at' => now(),
            ]);

            if ($validated['decision'] === 'revised') {
                $lhpDocument->update([
                    'status' => 'revised',
                ]);

                LhpReview::query()
                ->where('lhp_document_id', $lhpDocument->id)
                ->where('id', '!=', $currentReview->id)
                ->delete();

                return response()->json([
                    'message' => 'LHP ditolak',
                ]);
            }

            /*
             * =========================
             * VALIDASI WORKFLOW
             * =========================
             */
            if ($lhpDocument->status !== 'in_review') {
                abort(400, 'LHP tidak dalam proses review');
            }

            if (!$currentReview) {
                abort(400, 'Tidak ada review aktif');
            }

            $nextStep = LhpStep::where('sequence_order', '>', $currentReview->reviewStep->sequence_order)
            ->orderBy('sequence_order')
            ->first();

            if ($nextStep) {
                LhpReview::create([
                    'lhp_document_id' => $lhpDocument->id,
                    'lhp_step_id' => $nextStep->id,
                    'role' => $nextStep->code,
                    'decision' => 'pending',
                ]);

                return response()->json([
                    'message' => 'LHP disetujui, lanjut ke tahap berikutnya',
                ]);
            }

            $lhpDocument->update([
                'status' => 'validated',
            ]);

            return response()->json([
                'message' => 'LHP disetujui sepenuhnya',
            ]);
        });
    }

    public function fillAnalysis(Request $request)
    {
        $validated = $request->validate([
            'lhp_document_id' => ['required', 'exists:lhp_documents,id'],
            'lhp' => ['required', 'array', 'min:1'],
            'lhp.*.lhp_document_detail_id' => ['required', 'exists:lhp_document_details,id'],
            'lhp.*.parameters' => ['required', 'array', 'min:1'],
            'lhp.*.parameters.*.id' => ['required', 'exists:offer_sample_parameters,id'],
            'lhp.*.parameters.*.result' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated) {
            $lhp = LhpDocument::lockForUpdate()->findOrFail($validated['lhp_document_id']);

            /*
            |--------------------------------------------------------------------------
            | VALIDASI STATUS (INI KUNCI)
            |--------------------------------------------------------------------------
            */
            if (!in_array($lhp->status, ['draft', 'in_analysis', 'revised'])) {
                abort(403, 'LHP tidak dapat diisi pada status saat ini');
            }

            /*
            |--------------------------------------------------------------------------
            | SIMPAN HASIL ANALISIS
            |--------------------------------------------------------------------------
            */
            foreach ($validated['lhp'] as $detail) {
                // pastikan detail milik LHP ini
                if (!$lhp->details()->where('id', $detail['lhp_document_detail_id'])->exists()) {
                    abort(422, 'Detail LHP tidak valid');
                }

                foreach ($detail['parameters'] as $param) {
                    LhpDocumentParameter::updateOrCreate(
                        [
                            'lhp_document_detail_id' => $detail['lhp_document_detail_id'],
                            'offer_sample_parameter_id' => $param['id'],
                        ],
                        [
                            'result' => $param['result'],
                        ]
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | SET STATUS → in_analysis
            |--------------------------------------------------------------------------
            */
            if ($lhp->status === 'draft' || $lhp->status === 'revised') {
                $lhp->update([
                    'status' => 'in_analysis',
                ]);
            }

            LhpReview::create([
                'lhp_document_id' => $lhp->id,
                'role' => 'penyelia_lab',
                'lhp_step_id' => 2,
                'decision' => 'pending',
                'reviewed_by' => auth()->user()->name,
            ]);

            return response()->json([
                'message' => 'Hasil analisis berhasil disimpan',
                'status' => $lhp->status,
            ]);
        });
    }
}
