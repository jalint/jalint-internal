<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOfferRequest;
use App\Http\Requests\UpdateOfferRequest;
use App\Models\Offer;
use App\Models\OfferReview;
use App\Models\OfferSampleParameter;
use App\Models\ReviewStep;
use App\Models\User;
use App\Services\OfferStatusResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfferController extends Controller
{
    public function summary(Request $request)
    {
        $role = auth()->user()->roles->first()->name;

        $base = Offer::query()
            ->with('currentReview.reviewStep');

        if (!$request->filled('start_date') && !$request->filled('end_date')) {
            $base->whereBetween('offer_date', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]);
        }

        if ($request->filled('start_date')) {
            $base->whereDate('offer_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $base->whereDate('offer_date', '<=', $request->end_date);
        }

        $summary = [];
        $summary['all'] = (clone $base)->count();

        switch ($role) {
            /*
            |--------------------------------------------------------------------------
            | ADMIN PENAWARAN
            |--------------------------------------------------------------------------
            */
            case 'admin_penawaran':
                $summary['draft'] = (clone $base)
                    ->where('status', 'draft')
                    ->count();

                $summary['proses_kaji_ulang'] = (clone $base)
                    ->where('status', 'in_review')
                    ->whereHas(
                        'currentReview.reviewStep',
                        fn ($q) => $q->where('code', '!=', 'admin_penawaran')
                    )
                    ->count();

                // ✅ Cek Pembayaran Pelanggan (pending ONLY)
                $summary['cek_pembayaran_pelanggan'] = (clone $base)
                    ->where('status', 'approved')
                    ->whereHas(
                        'invoice.payments',
                        fn ($q) => $q->where('status', 'pending')
                    )
                    ->whereDoesntHave(
                        'invoice.payments',
                        fn ($q) => $q->where('status', 'approved')
                    )
                    ->count();

                // ✅ Proses Pengujian (approved payment exists)
                $summary['proses_pengujian'] = (clone $base)
                ->where(function ($q) {
                    $q->where('is_dp', 0)
                        ->orWhere(function ($qq) {
                            $qq->where('status', 'approved')
                            ->whereHas('invoice.payments', fn ($p) => $p->where('status', 'approved'));
                        });
                })
                 ->count();

                $summary['verif_pelanggan'] = (clone $base)
                    ->where('created_by_type', 'customer')
                    ->whereHas('currentReview', fn ($q) => $q->where('decision', 'pending')
                          ->whereHas(
                              'reviewStep',
                              fn ($qs) => $qs->where('code', 'admin_penawaran')
                          )
                    )
                    ->count();

                $summary['approved'] = (clone $base)
                    ->where('status', 'approved')
                    ->doesntHave('invoice')
                    ->count();

                $summary['completed'] = (clone $base)
                    ->where('status', 'completed')
                    ->count();

                $summary['rejected'] = (clone $base)
                    ->where('status', 'rejected')
                    ->count();

                break;

                /*
                |--------------------------------------------------------------------------
                | ADMIN KUPTDK
                |--------------------------------------------------------------------------
                */
            case 'admin_kuptdk':
                $summary['kaji_ulang'] = (clone $base)
                    ->whereHas(
                        'currentReview.reviewStep',
                        fn ($q) => $q->where('code', 'admin_kuptdk')
                    )
                    ->count();

                $summary['waiting_ma'] = (clone $base)
                ->whereHas(
                    'currentReview.reviewStep',
                    fn ($q) => $q->where('code', 'manager_admin')
                )
                ->count();

                $summary['approved_ma'] = (clone $base)
                    ->where('status', 'in_review')
                    ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')
                          ->whereHas(
                              'reviewStep',
                              fn ($qs) => $qs->where('code', 'manager_admin')
                          )
                    )
                    ->whereHas(
                        'currentReview.reviewStep',
                        fn ($q) => $q->where('code', 'manager_teknis')
                    )
                    ->count();

                $summary['approved_mt'] = (clone $base)
                    ->where('status', 'in_review')
                    ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')
                          ->whereHas(
                              'reviewStep',
                              fn ($qs) => $qs->where('code', 'manager_teknis')
                          )
                    )
                    ->whereHas(
                        'currentReview.reviewStep',
                        fn ($q) => $q->where('code', 'customer')
                    )
                    ->count();

                $summary['approved'] = (clone $base)
                    ->where('status', 'approved')
                    ->count();

                $summary['completed'] = (clone $base)
                    ->where('status', 'completed')
                    ->count();

                $summary['rejected'] = (clone $base)
                    ->where('status', 'rejected')
                    ->count();

                break;

                /*
                |--------------------------------------------------------------------------
                | MANAGER ADMIN
                |--------------------------------------------------------------------------
                */
            case 'manager_admin':
                $summary['verifikasi_kaji_ulang'] = (clone $base)
                    ->whereHas(
                        'currentReview.reviewStep',
                        fn ($q) => $q->where('code', 'manager_admin')
                    )
                    ->count();

                $summary['waiting_mt'] = (clone $base)
                    ->where('status', 'in_review')
                    ->whereHas('currentReview', fn ($q) => $q
                          ->whereHas(
                              'reviewStep',
                              fn ($qs) => $qs->where('code', 'manager_teknis')
                          )
                    )
                    ->count();

                $summary['approved'] = (clone $base)
                    ->where('status', 'approved')
                    ->count();

                $summary['completed'] = (clone $base)
                    ->where('status', 'completed')
                    ->count();

                $summary['rejected'] = (clone $base)
                    ->where('status', 'rejected')
                    ->count();

                break;

                /*
                |--------------------------------------------------------------------------
                | MANAGER TEKNIS
                |--------------------------------------------------------------------------
                */
            case 'manager_teknis':
                $summary['verifikasi_kaji_ulang'] = (clone $base)
                    ->whereHas(
                        'currentReview.reviewStep',
                        fn ($q) => $q->where('code', 'manager_teknis')
                    )
                    ->count();

                $summary['approved_mt'] = (clone $base)
                    ->where('status', 'in_review')
                    ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')
                          ->whereHas(
                              'reviewStep',
                              fn ($qs) => $qs->where('code', 'manager_teknis')
                          )
                    )
                    ->count();

                $summary['approved'] = (clone $base)
                    ->where('status', 'approved')
                    ->count();

                $summary['completed'] = (clone $base)
                    ->where('status', 'completed')
                    ->count();

                $summary['rejected'] = (clone $base)
                    ->where('status', 'rejected')
                    ->count();

                break;
        }

        return response()->json($summary);
    }

    public function index(Request $request)
    {
        $role = auth()->user()->roles->first()->name;
        $filter = $request->query('filter', 'all');

        $query = Offer::query()
            ->with([
                'customer:id,name',
                'currentReview.reviewStep',
            ])
            ->orderByDesc('created_at');

        switch ($role) {
            case 'admin_penawaran':
                match ($filter) {
                    'draft' => $query->where('status', 'draft'),

                    'verif_pelanggan' => $query
                        ->where('created_by_type', 'customer')
                        ->whereHas('currentReview', fn ($q) => $q->where('decision', 'pending')
                              ->whereHas('reviewStep', fn ($qs) => $qs->where('code', 'admin_penawaran')
                              )
                        ),

                    'proses_kaji_ulang' => $query
                        ->where('status', 'in_review')
                        ->whereHas('currentReview', fn ($q) => $q->where('decision', 'pending')
                                ->whereHas('reviewStep', fn ($qs) => $qs->where('code', '!=', 'admin_penawaran')
                                )
                        ),

                    'cek_pembayaran_pelanggan' => $query->where('status', 'approved')->whereHas('invoice.payments', fn ($q) => $q->where('status', 'pending')
                    ),

                    'proses_pengujian' => $query->where(function ($q) {
                        $q->where('is_dp', 0)
                            ->orWhere(function ($qq) {
                                $qq->where('status', 'approved')
                                ->whereHas('invoice.payments', fn ($p) => $p->where('status', 'approved'));
                            });
                    }),

                    default => null,
                };
                break;

            case 'admin_kuptdk':
                match ($filter) {
                    'approved_ma' => $query->where('status', 'in_review')
                    ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')
                          ->whereHas(
                              'reviewStep',
                              fn ($qs) => $qs->where('code', 'manager_admin')
                          )
                    )
                    ->whereHas(
                        'currentReview.reviewStep',
                        fn ($q) => $q->where('code', 'manager_teknis')
                    ),
                    'approved_mt' => $query->where('status', 'in_review')
                    ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')
                          ->whereHas(
                              'reviewStep',
                              fn ($qs) => $qs->where('code', 'manager_teknis')
                          )
                    )
                    ->whereHas(
                        'currentReview.reviewStep',
                        fn ($q) => $q->where('code', 'customer')
                    ),
                    'kaji_ulang' => $this->whereCurrentReview($query, 'admin_kuptdk'),
                    'waiting_ma' => $this->whereCurrentReview($query, 'manager_admin', 'pending'),
                    default => null,
                };
                break;

            case 'manager_admin':
                match ($filter) {
                    'verifikasi' => $this->whereCurrentReview($query, 'manager_admin'),
                    'waiting_mt' => $this->whereCurrentReview($query, 'manager_teknis', 'pending'),
                    default => null,
                };
                break;

            case 'manager_teknis':
                match ($filter) {
                    'verifikasi_kaji_ulang' => $this->whereCurrentReview($query, 'manager_teknis'),
                    'approved_mt' => $query->where('status', 'in_review')
                    ->whereHas('reviews', fn ($q) => $q->where('decision', 'approved')
                          ->whereHas(
                              'reviewStep',
                              fn ($qs) => $qs->where('code', 'manager_teknis')
                          )
                    ),
                    default => null,
                };
                break;
        }

        // ===== FINAL STATE FILTER =====
        if (in_array($filter, ['approved', 'completed', 'rejected'])) {
            $query->where('status', $filter);
        }

        // ===== SEARCH =====
        if ($search = $request->search) {
            $query->where(fn ($q) => $q->where('offer_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
            );
        }

        // ===== DATE FILTER =====
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if ($startDate || $endDate) {
            $query->whereBetween('offer_date', [
                $startDate ? now()->parse($startDate)->startOfDay() : now()->subYears(50),
                $endDate ? now()->parse($endDate)->endOfDay() : now()->addYears(50),
            ]);
        } else {
            $query->whereBetween('offer_date', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]);
        }

        $offers = $query->paginate($request->per_page ?? 15);

        $offers->getCollection()->transform(function ($offer) use ($role) {
            $offer->display_status = OfferStatusResolver::resolve($offer, $role);

            return $offer;
        });

        return response()->json($offers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOfferRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $status = $request->boolean('is_draft')
                    ? 'draft'
                    : 'in_review';

                $offer = Offer::create([
                    'offer_number' => $this->generateOfferNumber(),
                    'title' => $request->title,
                    'customer_id' => $request->customer_id,
                    'offer_date' => $request->offer_date,
                    'expired_date' => $request->expired_date,
                    'request_number' => $request->request_number,
                    'template_id' => $request->template_id,
                    'additional_description' => $request->additional_description,
                    'location' => $request->location,
                    'testing_activities' => $request->testing_activities,
                    'discount_amount' => $request->discount_amount ?? 0,
                    'vat_percent' => $request->vat_percent ?? 0,
                    'withholding_tax_percent' => $request->withholding_tax_percent ?? 0,
                    'status' => $status,
                    'created_by_id' => auth()->id(),
                    'created_by_type' => 'admin',
                ]);

                /* =====================
                 | CREATE SAMPLES & PARAMETERS
                 ===================== */
                foreach ($request->samples as $sampleData) {
                    $sample = $offer->samples()->create([
                        'title' => $sampleData['title'],
                    ]);

                    foreach ($sampleData['parameters'] as $param) {
                        $sample->parameters()->create([
                            'test_parameter_id' => $param['test_parameter_id'],
                            'test_package_id' => $param['test_package_id'] ?? null,
                            'price' => $param['unit_price'],
                            'qty' => $param['qty'],
                            'subkon_id' => 1, // default internal
                        ]);
                    }
                }

                /* =====================
                 | REVIEW WORKFLOW
                 ===================== */
                if ($status === 'in_review') {
                    $firstStep = ReviewStep::where('code', 'admin_kuptdk')->firstOrFail();

                    OfferReview::create([
                        'offer_id' => $offer->id,
                        'review_step_id' => $firstStep->id,
                        'decision' => 'pending',
                    ]);
                }

                return response()->json($offer->load('samples.parameters'), 201);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to create offer', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan saat membuat penawaran',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $offer = Offer::with([
            'template',
            'customer' => function ($query) {
                $query->select(['id', 'name'])->with('customerContact:id,customer_id,name,position,phone');
            },

            // review aktif
            'currentReview.reviewStep',

            // history review (tanpa pending)
            'reviews' => function ($q) {
                $q->where('decision', '!=', 'pending')
                  ->orderBy('created_at');
            },
            'reviews.reviewStep',

            // RAW DATA (akan di-hide)
            'samples.parameters.subkon',
            'samples.parameters.testParameter.sampleType',
            'documents',
            'invoice.payments',
        ])->findOrFail($id);

        $currentReview = $offer->currentReview;

        $canReview =
            $offer->status === 'in_review'
            && $currentReview
            && auth()->check()
            && auth()->user()->hasRole($currentReview->reviewStep->code);

        /**
         * =========================
         * FORMAT SAMPLE DATA
         * sample → sample_type → parameters
         * =========================.
         */
        $samples = $offer->samples->map(function ($sample) {
            $sampleParameters = $sample->parameters
                ->groupBy(fn ($param) => $param->testParameter->sample_type_id)
                ->map(function ($items) {
                    $sampleType = $items->first()->testParameter->sampleType;

                    return [
                        'sample_type' => [
                            'id' => $sampleType?->id,
                            'name' => $sampleType?->name,
                            'regulation' => $sampleType?->regulation,
                        ],

                        'parameters' => $items->map(function ($param) {
                            return [
                                'id' => $param->id,
                                'offer_sample_id' => $param->offer_sample_id,
                                'test_parameter_id' => $param->testParameter->id,
                                'name' => $param->testParameter->name,
                                'status' => $param->testParameter->status,
                                'method' => $param->testParameter->method ?? null,
                                'unit_price' => $param->price,
                                'qty' => $param->qty,
                                'subtotal' => $param->price * $param->qty,
                                'subkon' => $param->subkon ? [
                                    'id' => $param->subkon->id,
                                    'name' => $param->subkon->name,
                                ] : null,
                            ];
                        })->values(),
                    ];
                })
                ->values();

            return [
                'id' => $sample->id,
                'title' => $sample->title,
                'sample_parameters' => $sampleParameters,
            ];
        });

        /*
         * HIDE RELASI MENTAH
         */
        $offer->makeHidden(['samples']);

        return response()->json([
            'can_review' => $canReview,
            'offer' => $offer,
            'current_step' => $currentReview ? [
                'code' => $currentReview->reviewStep->code,
                'name' => $currentReview->reviewStep->name,
            ] : null,
            'samples' => $samples,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOfferRequest $request, $id)
    {
        $user = auth()->user();

        return DB::transaction(function () use ($request, $id, $user) {
            /**
             * 1️⃣ Ambil offer + lock.
             */
            $offer = Offer::with([
                'reviews.reviewStep',
                'samples.parameters',
            ])
                ->lockForUpdate()
                ->findOrFail($id);

            /*
             * 2️⃣ Validasi STATE
             */
            if ($offer->status !== 'rejected') {
                abort(400, 'Penawaran hanya dapat direvisi jika status rejected');
            }

            /*
             * 3️⃣ Validasi ROLE
             */
            if (!$user->hasRole('admin_penawaran')) {
                abort(403, 'Hanya Admin Penawaran yang dapat merevisi penawaran');
            }

            /**
             * 4️⃣ Pastikan review terakhir = rejected.
             */
            $lastReview = $offer->reviews()
                ->latest('created_at')
                ->first();

            if (!$lastReview || $lastReview->decision !== 'rejected') {
                abort(400, 'Penawaran tidak berada pada kondisi revisi');
            }

            /*
             * 5️⃣ Update OFFER HEADER
             */
            $offer->update([
                'title' => $request->title,
                'customer_id' => $request->customer_id,
                'customer_contact_id' => $request->customer_contact_id,
                'offer_date' => $request->offer_date,
                'expired_date' => $request->expired_date,
                'request_number' => $request->request_number,
                'template_id' => $request->template_id,
                'additional_description' => $request->additional_description,
                'location' => $request->location,
                'testing_activities' => $request->testing_activities,
                'discount_amount' => $request->discount_amount ?? 0,
                'vat_percent' => $request->vat_percent ?? 0,
                'withholding_tax_percent' => $request->withholding_tax_percent ?? 0,
                'status' => 'in_review',
            ]);

            /*
             * 6️⃣ RESET SAMPLE & PARAMETER
             * (hapus & recreate → aman untuk revisi)
             */
            $offer->samples()->delete();

            foreach ($request->samples as $sampleData) {
                $sample = $offer->samples()->create([
                    'title' => $sampleData['title'],
                ]);

                foreach ($sampleData['parameters'] as $param) {
                    $sample->parameters()->create([
                        'test_parameter_id' => $param['test_parameter_id'],
                        'test_package_id' => $param['test_package_id'] ?? null,
                        'price' => $param['unit_price'],
                        'qty' => $param['qty'],
                        'subkon_id' => 1, // default internal
                    ]);
                }
            }

            /**
             * 7️⃣ SNAPSHOT: Admin KUPTDK AUTO APPROVE.
             */
            $adminPenawaranStep = ReviewStep::where('code', 'admin_penawaran')->firstOrFail();

            OfferReview::create([
                'offer_id' => $offer->id,
                'review_step_id' => $adminPenawaranStep->id,
                'decision' => 'approved',
                'reviewer_id' => $user->id,
                'reviewed_at' => now(),
                'note' => 'Revisi penawaran setelah penolakan',
            ]);

            /**
             * 8️⃣ LANJUT KE STEP BERIKUTNYA (Manager Admin).
             */
            $nextStep = ReviewStep::where(
                'sequence_order',
                '>',
                $adminPenawaranStep->sequence_order
            )
                ->orderBy('sequence_order')
                ->first();

            if (!$nextStep) {
                abort(500, 'Step review selanjutnya tidak ditemukan');
            }

            OfferReview::create([
                'offer_id' => $offer->id,
                'review_step_id' => $nextStep->id,
                'decision' => 'pending',
            ]);

            /*
             * 9️⃣ DONE
             */
            return response()->json([
                'message' => 'Penawaran berhasil direvisi dan dikirim ulang untuk review',
            ]);
        });
    }

    public function review(Request $request, $id)
    {
        $user = auth()->user();

        $rules = [
            'decision' => 'required|in:approved,rejected',
            'note' => 'nullable|string',
        ];

        /*
         * =========================
         * ADMIN KUPTDK – WAJIB DETAIL
         * =========================
         */
        if ($user->hasRole('admin_kuptdk') && $request->decision != 'rejected') {
            $rules = array_merge($rules, [
                'testing_activities' => 'required|string',
                'details' => 'required|array|min:1',
                'details.*.id' => 'required|exists:offer_sample_parameters,id',
                'details.*.test_parameter_id' => 'required|exists:test_parameters,id',
                'details.*.subkon_id' => 'required|exists:subkons,id',
                'details.*.price' => 'required|numeric|min:0',
                'details.*.qty' => 'required|integer|min:1',
                'details.*.is_delete' => 'sometimes|boolean',
            ]);
        }

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated, $id, $user) {
            $offer = Offer::with([
                'currentReview.reviewStep',
                'samples.parameters',
            ])
                ->lockForUpdate()
                ->findOrFail($id);

            /*
             * =========================
             * VALIDASI WORKFLOW
             * =========================
             */
            if ($offer->status !== 'in_review') {
                abort(400, 'Offer tidak dalam proses review');
            }

            $currentReview = $offer->currentReview;
            if (!$currentReview) {
                abort(400, 'Tidak ada review aktif');
            }

            if (!$user->hasRole($currentReview->reviewStep->code)) {
                abort(403, 'Anda tidak berhak mereview penawaran ini');
            }

            /*
             * =========================
             * UPDATE REVIEW SAAT INI
             * =========================
             */
            $currentReview->update([
                'decision' => $validated['decision'],
                'note' => $validated['note'] ?? null,
                'reviewer_id' => $user->id,
                'reviewed_at' => now(),
            ]);

            /*
             * =========================
             * JIKA REJECT → STOP
             * =========================
             */
            if ($validated['decision'] === 'rejected') {
                $offer->update([
                    'status' => 'rejected',
                ]);

                // $nextStep = ReviewStep::where('code', 'admin_kuptdk')
                // ->first();

                // OfferReview::create([
                //     'offer_id' => $offer->id,
                //     'review_step_id' => $nextStep->id,
                //     'decision' => 'pending',
                // ]);

                return response()->json([
                    'message' => 'Penawaran ditolak',
                ]);
            }

            /*
             * =========================
             * ADMIN KUPTDK – UPDATE PARAMETER
             * =========================
             */
            if ($currentReview->reviewStep->code === 'admin_kuptdk') {
                foreach ($validated['details'] as $detail) {
                    $param = OfferSampleParameter::whereHas('sample', function ($q) use ($offer) {
                        $q->where('offer_id', $offer->id);
                    })
                        ->lockForUpdate()
                        ->findOrFail($detail['id']);

                    if (!empty($detail['is_delete']) && $detail['is_delete']) {
                        $param->delete();
                    } else {
                        $param->update([
                            'test_parameter_id' => $detail['test_parameter_id'],
                            'subkon_id' => $detail['subkon_id'],
                            'price' => $detail['price'],
                            'qty' => $detail['qty'],
                        ]);
                    }
                }

                $offer->update([
                    'testing_activities' => $validated['testing_activities'],
                ]);
            }

            /**
             * =========================
             * STEP BERIKUTNYA
             * =========================.
             */
            $nextStep = ReviewStep::where('sequence_order', '>', $currentReview->reviewStep->sequence_order)
                ->orderBy('sequence_order')
                ->first();

            if ($nextStep) {
                OfferReview::create([
                    'offer_id' => $offer->id,
                    'review_step_id' => $nextStep->id,
                    'decision' => 'pending',
                ]);

                return response()->json([
                    'message' => 'Review disetujui, lanjut ke tahap berikutnya',
                ]);
            }

            /*
             * =========================
             * FINAL APPROVE
             * =========================
             */
            $offer->update([
                'status' => 'approved',
            ]);

            return response()->json([
                'message' => 'Penawaran disetujui sepenuhnya',
            ]);
        });
    }

    public function cancel(Request $request, $id)
    {
        $user = auth()->user();

        return DB::transaction(function () use ($id, $user, $request) {
            $offer = Offer::lockForUpdate()->findOrFail($id);

            // 1. Tidak boleh cancel kalau sudah approved
            if ($offer->status === 'approved') {
                abort(400, 'Penawaran yang sudah disetujui tidak dapat dibatalkan');
            }

            // 2. Tidak boleh cancel dua kali
            if ($offer->status === 'cancelled') {
                abort(400, 'Penawaran sudah dibatalkan');
            }

            // 3. (Opsional) Validasi hak akses
            // contoh: hanya admin_penawaran / admin_kuptdk
            if (!$user->hasAnyRole(['admin_penawaran', 'admin_kuptdk'])) {
                abort(403, 'Anda tidak berhak membatalkan penawaran');
            }

            // 4. Update status offer
            $offer->update([
                'status' => 'cancelled',
            ]);

            // 5. Tutup review aktif (jika ada)
            OfferReview::where('offer_id', $offer->id)
                ->where('decision', 'pending')
                ->update([
                    'decision' => 'cancelled',
                    'reviewer_id' => $user->id,
                    'reviewed_at' => now(),
                    'note' => $request->note ?? 'Penawaran dibatalkan',
                ]);

            return response()->json([
                'message' => 'Penawaran berhasil dibatalkan',
            ]);
        });
    }

    protected function generateOfferNumber(): string
    {
        $now = Carbon::now();

        $year = $now->year;
        $monthRoman = $this->toRoman($now->month);

        $lastOffer = Offer::whereYear('created_at', $year)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $lastSequence = 0;

        if ($lastOffer) {
            $parts = explode('/', $lastOffer->offer_number);
            $lastSequence = (int) ($parts[0] ?? 0);
        }

        $sequence = $lastSequence + 1;

        $sequenceFormatted = str_pad($sequence, 4, '0', STR_PAD_LEFT);

        $unitCode = config('offer.unit_code', 'Jalint-Lab');

        return "{$sequenceFormatted}/{$unitCode}/{$monthRoman}/{$year}";
    }

    protected function toRoman(int $month): string
    {
        $map = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        return $map[$month];
    }

    public function canReview(Offer $offer, User $user): bool
    {
        if ($offer->status !== 'in_review') {
            return false;
        }

        $currentReview = $offer->reviews
            ->where('decision', 'pending')
            ->first();

        if (!$currentReview) {
            return false;
        }

        return $currentReview->reviewStep->code === $user->role;
    }

    private function baseRoleQuery(string $role)
    {
        return Offer::query()
            ->with(['customer:id,name', 'currentReview.reviewStep'])
            ->when($role !== 'admin_penawaran', function ($q) {
                // admin_penawaran boleh lihat draft
                $q->where('status', '!=', 'draft');
            });
    }

    protected function whereCurrentReview($query, string $step, ?string $decision = null)
    {
        return $query->whereHas('currentReview', function ($q) use ($step, $decision) {
            $q->whereHas('reviewStep', fn ($qs) => $qs->where('code', $step));

            if ($decision) {
                $q->where('decision', $decision);
            }
        });
    }
}
