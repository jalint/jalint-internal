<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOfferRequest;
use App\Http\Requests\UpdateOfferRequest;
use App\Models\Offer;
use App\Models\OfferReview;
use App\Models\OfferSampleParameter;
use App\Models\ReviewStep;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfferController extends Controller
{
    public function summary()
    {
        $user = auth()->user();
        $role = $user->roles->first()->name;

        $baseQuery = Offer::query()
            ->with('currentReview.reviewStep');

        $summary = [];

        /*
         * =========================
         * SEMUA ROLE
         * =========================
         */
        $summary['all'] = (clone $baseQuery)->count();

        /*
         * =========================
         * ADMIN PENAWARAN
         * =========================
         */
        if ($role === 'admin_penawaran') {
            $summary['draft'] = (clone $baseQuery)
                ->where('status', 'draft')
                ->count();

            $summary['in_review'] = (clone $baseQuery)
                ->where('status', 'in_review')
                ->count();

            $summary['waiting_customer_validation'] = (clone $baseQuery)
                ->where('created_by_type', 'customer')
                ->where('status', 'in_review')
                ->whereHas('currentReview.reviewStep', function ($q) {
                    $q->where('code', 'admin_penawaran');
                })
                ->count();
        }

        /*
         * =========================
         * ADMIN KUPTDK
         * =========================
         */
        if ($role === 'admin_kuptdk') {
            $summary['kaji_ulang'] = (clone $baseQuery)
                ->where('status', 'in_review')
                ->whereHas('currentReview.reviewStep', function ($q) {
                    $q->where('code', 'admin_kuptdk');
                })
                ->count();

            $summary['waiting_ma'] = (clone $baseQuery)
                ->where('status', 'in_review')
                ->whereHas('currentReview.reviewStep', function ($q) {
                    $q->where('code', 'manager_admin');
                })
                ->count();
        }

        /*
         * =========================
         * MANAGER ADMIN
         * =========================
         */
        if ($role === 'manager_admin') {
            $summary['verifikasi_kaji_ulang'] = (clone $baseQuery)
                ->where('status', 'in_review')
                ->whereHas('currentReview.reviewStep', function ($q) {
                    $q->where('code', 'manager_admin');
                })
                ->count();

            $summary['waiting_mt'] = (clone $baseQuery)
                ->where('status', 'in_review')
                ->whereHas('currentReview.reviewStep', function ($q) {
                    $q->where('code', 'manager_teknis');
                })
                ->count();
        }

        /*
         * =========================
         * MANAGER TEKNIS
         * =========================
         */
        if ($role === 'manager_teknis') {
            $summary['verifikasi_kaji_ulang'] = (clone $baseQuery)
                ->where('status', 'in_review')
                ->whereHas('currentReview.reviewStep', function ($q) {
                    $q->where('code', 'manager_teknis');
                })
                ->count();

            $summary['approved'] = (clone $baseQuery)
                ->where('status', 'approved')
                ->count();

            $summary['rejected'] = (clone $baseQuery)
                ->where('status', 'rejected')
                ->count();
        }

        return response()->json($summary);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $user->roles->first()->name; // spatie
        $filter = $request->query('filter', 'all');

        $query = Offer::query()
            ->with([
                'customer:id,name',
                'currentReview.reviewStep',
            ])
            ->orderByDesc('created_at');

        /*
         * =========================
         * FILTER GLOBAL (SEMUA ROLE)
         * =========================
         */
        if ($filter === 'draft') {
            $query->where('status', 'draft');
        }

        if ($filter === 'in_review') {
            $query->where('status', 'in_review');
        }

        if ($filter === 'approved') {
            $query->where('status', 'approved');
        }

        if ($filter === 'rejected') {
            $query->where('status', 'rejected');
        }

        /*
         * =========================
         * FILTER BERDASARKAN ROLE
         * =========================
         */
        switch ($role) {
            /*
             * =========================
             * ADMIN PENAWARAN
             * =========================
             */
            case 'admin_penawaran':
                if ($filter === 'waiting_customer_validation') {
                    $query
                        ->where('created_by_type', 'customer')
                        ->where('status', 'in_review')
                        ->whereHas('currentReview.reviewStep', function ($q) {
                            $q->where('code', 'admin_penawaran');
                        });
                }

                break;

                /*
                 * =========================
                 * ADMIN KUPTDK
                 * =========================
                 */
            case 'admin_kuptdk':
                if ($filter === 'kaji_ulang') {
                    $query
                        ->where('status', 'in_review')
                        ->whereHas('currentReview.reviewStep', function ($q) {
                            $q->where('code', 'admin_kuptdk');
                        });
                }

                if ($filter === 'waiting_ma') {
                    $query
                        ->where('status', 'in_review')
                        ->whereHas('currentReview.reviewStep', function ($q) {
                            $q->where('code', 'manager_admin');
                        });
                }

                break;

                /*
                 * =========================
                 * MANAGER ADMIN
                 * =========================
                 */
            case 'manager_admin':
                if ($filter === 'verifikasi_kaji_ulang') {
                    $query
                        ->where('status', 'in_review')
                        ->whereHas('currentReview.reviewStep', function ($q) {
                            $q->where('code', 'manager_admin');
                        });
                }

                if ($filter === 'waiting_mt') {
                    $query
                        ->where('status', 'in_review')
                        ->whereHas('currentReview.reviewStep', function ($q) {
                            $q->where('code', 'manager_teknis');
                        });
                }

                break;

                /*
                 * =========================
                 * MANAGER TEKNIS
                 * =========================
                 */
            case 'manager_teknis':
                if ($filter === 'verifikasi_kaji_ulang') {
                    $query
                        ->where('status', 'in_review')
                        ->whereHas('currentReview.reviewStep', function ($q) {
                            $q->where('code', 'manager_teknis');
                        });
                }

                if ($filter === 'approved') {
                    $query->where('status', 'approved');
                }

                if ($filter === 'rejected') {
                    $query->where('status', 'rejected');
                }

                break;
        }

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('offer_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereDate('offer_date', $search)
                  ->orWhereDate('expired_date', $search)
                  ->orWhere('status', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate($request->per_page ? $request->per_page : 15));
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
            'samples.parameters.testParameter.sampleType.regulation',
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
                            'regulation' => $sampleType && $sampleType->regulation ? [
                                'id' => $sampleType->regulation->id,
                                'name' => $sampleType->regulation->name,
                            ] : null,
                        ],

                        'parameters' => $items->map(function ($param) {
                            return [
                                'id' => $param->testParameter->id,
                                'name' => $param->testParameter->name,
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
            if (!$user->hasRole('admin_kuptdk')) {
                abort(403, 'Hanya Admin KUPTDK yang dapat merevisi penawaran');
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
            $adminKuptdkStep = ReviewStep::where('code', 'admin_kuptdk')->firstOrFail();

            OfferReview::create([
                'offer_id' => $offer->id,
                'review_step_id' => $adminKuptdkStep->id,
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
                $adminKuptdkStep->sequence_order
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
        if ($user->hasRole('admin_kuptdk')) {
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
}
