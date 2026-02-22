<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerOfferRequest;
use App\Models\Offer;
use App\Models\OfferReview;
use App\Models\ReviewStep;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerOfferController extends Controller
{
    public function summary(Request $request)
    {
        $customerId = auth('customer')->user()->customer_id;

        $base = Offer::query()
            ->where('customer_id', $customerId);

        // if (!$request->filled('start_date') && !$request->filled('end_date')) {
        //     $base->whereBetween('offer_date', [
        //         now()->startOfMonth(),
        //         now()->endOfMonth(),
        //     ]);
        // }

        // =========================
        // FILTER TANGGAL MANUAL
        // =========================
        // if ($request->filled('start_date')) {
        //     $base->whereDate(
        //         'offer_date',
        //         '>=',
        //         Carbon::createFromFormat('Y-m-d', $request->start_date)
        //     );
        // }

        // if ($request->filled('end_date')) {
        //     $base->whereDate(
        //         'offer_date',
        //         '<=',
        //         Carbon::createFromFormat('Y-m-d', $request->end_date)
        //     );
        // }

        return response()->json([
            'all' => (clone $base)->count(),

            'draft' => (clone $base)
                ->where('status', 'draft')
                ->count(),

            'penawaran_diproses' => (clone $base)
             ->where('status', 'in_review')
             ->whereHas(
                 'currentReview.reviewStep',
                 fn ($q) => $q->where('code', '!=', 'customer')
             )
             ->count(),

            'verifikasi_penawaran_final' => (clone $base)
             ->where('status', 'in_review')
             ->whereHas(
                 'currentReview.reviewStep',
                 fn ($q) => $q->where('code', 'customer')
             )
             ->count(),

            'proses_pembayaran_dp' => (clone $base)
                        ->where('status', 'approved')
                      ->where('is_dp', 1)
                      ->whereHas('invoice')
                      ->whereDoesntHave('invoice.payments')->count(),

            'verifikasi_pembayaran' => (clone $base)
                    ->where('status', 'approved')
                      ->where('is_dp', 1)
                      ->whereHas(
                          'invoice.payments',
                          fn ($p) => $p->where('status', 'pending')
                      )
                ->count(),

            'proses_pengujian' => (clone $base)
                 ->where('status', 'approved')
                ->where(function ($q) {
                    $q->where('is_dp', 0)
                        ->orWhere(function ($qq) {
                            $qq->where('is_dp', 1)
                                ->whereHas(
                                    'invoice.payments',
                                    fn ($p) => $p->where('status', 'approved')
                                );
                        });
                })->count(),

            'selesai' => (clone $base)
                ->where('status', 'completed')
                ->count(),

            'direvisi' => (clone $base)
                ->where('status', 'rejected')
                ->count(),
        ]);
    }

    public function index(Request $request)
    {
        $customerId = auth('customer')->user()->customer_id;

        // $request->validate([
        //     'start_date' => ['nullable', 'date_format:Y-m-d'],
        //     'end_date' => ['nullable', 'date_format:Y-m-d'],
        // ]);

        // =========================
        // BASE QUERY
        // =========================
        $query = Offer::query()
            ->where('customer_id', $customerId)
            ->with([
                'currentReview.reviewStep',
                'invoice.payments',
                'customer:id,name',
            ])
            ->orderByDesc('created_at');

        // =========================
        // DEFAULT BULAN BERJALAN
        // =========================
        // if (!$request->filled('start_date') && !$request->filled('end_date')) {
        //     $query->whereBetween('offer_date', [
        //         now()->startOfMonth(),
        //         now()->endOfMonth(),
        //     ]);
        // }

        // =========================
        // FILTER TANGGAL MANUAL
        // =========================
        // if ($request->filled('start_date')) {
        //     $query->whereDate(
        //         'offer_date',
        //         '>=',
        //         Carbon::createFromFormat('Y-m-d', $request->start_date)
        //     );
        // }

        // if ($request->filled('end_date')) {
        //     $query->whereDate(
        //         'offer_date',
        //         '<=',
        //         Carbon::createFromFormat('Y-m-d', $request->end_date)
        //     );
        // }

        // =========================
        // FILTER STATUS (SINKRON SUMMARY)
        // =========================
        switch ($request->filter) {
            case 'draft':
                $query->where('status', 'draft');
                break;

            case 'penawaran_diproses':
                $query->where('status', 'in_review')
                    ->whereHas(
                        'currentReview.reviewStep',
                        fn ($q) => $q->where('code', '!=', 'customer')
                    );
                break;

            case 'verifikasi_penawaran_final':
                $query->where('status', 'in_review')
                    ->whereHas(
                        'currentReview.reviewStep',
                        fn ($q) => $q->where('code', 'customer')
                    );
                break;

            case 'proses_pembayaran_dp':
                $query->where('status', 'approved')
                      ->where('is_dp', 1)
                      ->whereHas('invoice')
                      ->whereDoesntHave('invoice.payments');
                break;

            case 'verifikasi_pembayaran':
                $query->where('status', 'approved')
                      ->where('is_dp', 1)
                      ->whereHas(
                          'invoice.payments',
                          fn ($p) => $p->where('status', 'pending')
                      );
                break;

            case 'proses_pengujian':
                $query->where('status', 'approved')
                    ->where(function ($q) {
                        $q->where('is_dp', 0)
                          ->orWhere(function ($qq) {
                              $qq->where('is_dp', 1)
                                 ->whereHas(
                                     'invoice.payments',
                                     fn ($p) => $p->where('status', 'approved')
                                 );
                          });
                    });
                break;

            case 'completed':
                $query->where('status', 'completed');
                break;

            case 'rejected':
                $query->where('status', 'rejected');
                break;

            case 'all':
            default:
                // no additional filter
                break;
        }

        // =========================
        // SEARCH
        // =========================
        if ($search = $request->search) {
            $query->where(fn ($q) => $q->where('offer_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
            );
        }

        // =========================
        // PAGINATION
        // =========================
        $offers = $query->paginate($request->per_page ?? 15);

        // =========================
        // DISPLAY STATUS (SINKRON SUMMARY)
        // =========================
        $offers->getCollection()->transform(function ($offer) {
            $paymentPending = $offer->invoice?->payments
                ->where('status', 'pending')
                ->count() > 0;

            $paymentApproved = $offer->invoice?->payments
                ->where('status', 'approved')
                ->count() > 0;

            $currentStep = optional($offer->currentReview?->reviewStep)->code;

            $hasInvoice = $offer->invoice !== null;
            $hasPayment = $offer->invoice?->payments?->count() > 0;
            $paymentPending = $offer->invoice?->payments?->where('status', 'pending')->isNotEmpty();
            $paymentApproved = $offer->invoice?->payments?->where('status', 'approved')->isNotEmpty();

            $offer->display_status = match (true) {
                $offer->status === 'draft' => 'Draft',

                $offer->status === 'in_review' && $currentStep === 'customer' => 'Verifikasi Penawaran Final',

                $offer->status === 'in_review' => 'Penawaran Diproses',

                /*
                |--------------------------------------------------------------------------
                | DP FLOW
                |--------------------------------------------------------------------------
                */

                // Approved + DP + invoice ada + BELUM ADA payment
                $offer->status === 'approved'
                    && $offer->is_dp == 1
                    && $hasInvoice
                    && !$hasPayment => 'Proses Pembayaran DP',

                // Approved + DP + payment pending
                $offer->status === 'approved'
                    && $offer->is_dp == 1
                    && $paymentPending => 'Verifikasi Pembayaran',

                // Approved + DP + payment approved
                $offer->status === 'approved'
                    && $offer->is_dp == 1
                    && $paymentApproved => 'Proses Pengujian',

                /*
                |--------------------------------------------------------------------------
                | NON DP FLOW
                |--------------------------------------------------------------------------
                */

                $offer->status === 'approved'
                    && $offer->is_dp == 0 => 'Proses Pengujian',

                $offer->status == 'completed' => 'Completed',

                $offer->status == 'rejected' => 'Direvisi',

                default => ucfirst($offer->status),
            };

            return $offer;
        });

        return response()->json($offers);
    }

    public function store(StoreCustomerOfferRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $customerAccount = auth('customer')->user();
            $customerId = $customerAccount->customer_id;

            /*
             * 1️⃣ CREATE OFFER.
             */

            $isDraft = $request->boolean('is_draft') ? 'draft' : 'in_review';

            $offer = Offer::create([
                'offer_number' => $this->generateOfferNumber(),
                'title' => $request->title,
                'customer_id' => $customerId,
                'offer_date' => $request->offer_date,
                'expired_date' => $request->expired_date,
                'request_number' => $request->request_number,
                'template_id' => $request->template_id,
                'additional_description' => $request->additional_description,
                'location' => $request->location,
                'status' => $isDraft,
                'created_by_id' => $customerAccount->id,
                'created_by_type' => 'customer',
            ]);

            if ($isDraft == 'draft') {
                return response()->json(['message' => 'Data Draft penawaran berhasil dibuat']);
            }

            foreach ($request->samples as $sampleInput) {
                $sample = $offer->samples()->create([
                    'title' => $sampleInput['title'],
                ]);

                foreach ($sampleInput['parameters'] as $param) {
                    $sample->parameters()->create([
                        'test_parameter_id' => $param['test_parameter_id'],
                        'price' => $param['unit_price'],
                        'qty' => $param['qty'],
                        'subkon_id' => 1, // default internal
                    ]);
                }
            }

            /**
             * 3️⃣ FIRST REVIEW → ADMIN PENAWARAN.
             */
            $firstStep = ReviewStep::where('code', 'admin_penawaran')->firstOrFail();

            OfferReview::create([
                'offer_id' => $offer->id,
                'review_step_id' => $firstStep->id,
                'decision' => 'pending',
            ]);

            return response()->json([
                'message' => 'Penawaran berhasil dikirim',
                'offer_id' => $offer->id,
            ], 201);
        });
    }

    public function show($id)
    {
        $customerAccount = auth('customer')->user();

        $offer = Offer::with([
            'template',
            'samples.parameters.subkon',
            'samples.parameters.testParameter.sampleType',
            'documents',
            'invoice.payments',
        ])
        ->where('customer_id', $customerAccount->customer_id)
        ->findOrFail($id);

        /**
         * =========================
         * FORMAT SAMPLE DATA
         * =========================.
         */
        $samples = $offer->samples->map(function ($sample) {
            $groups = $sample->parameters
                ->groupBy(fn ($p) => $p->testParameter->sample_type_id)
                ->map(function ($items) {
                    $sampleType = $items->first()->testParameter->sampleType;

                    return [
                        'sample_type' => [
                            'id' => $sampleType?->id,
                            'name' => $sampleType?->name,
                            'regulation' => $sampleType->regulation,
                        ],

                        'parameters' => $items->map(function ($param) {
                            return [
                                'id' => $param->testParameter->id,
                                'name' => $param->testParameter->name,
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
                'sample_parameters' => $groups,
            ];
        });

        /*
         * RESPONSE
         */
        return response()->json([
            'offer' => [
                'id' => $offer->id,
                'offer_number' => $offer->offer_number,
                'title' => $offer->title,
                'offer_date' => $offer->offer_date,
                'expired_date' => $offer->expired_date,
                'status' => $offer->status,
                'template_id' => $offer->template_id,
                'location' => $offer->location,
                'subtotal_amount' => $offer->subtotal_amount,
                'additional_description' => $offer->additional_description,
                'vat_percent' => $offer->vat_percent,
                'ppn_amount' => $offer->ppn_amount,
                'pph_percent' => $offer->pph_percent,
                'pph_amount' => $offer->pph_amount,
                'total_amount' => $offer->total_amount,
                'discount_amount' => $offer->discount_amount,
                'template' => $offer->template,
                'documents' => $offer->documents,
                'invoice' => $offer->invoice,
            ],
            'samples' => $samples,
        ]);
    }

    public function reviewCustomer(Request $request, $id)
    {
        $customer = auth('customer')->user();

        $validated = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'note' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $id, $customer) {
            $offer = Offer::with([
                'currentReview.reviewStep',
            ])
                ->lockForUpdate()
                ->where('customer_id', $customer->customer_id)
                ->findOrFail($id);

            /*
             * =========================
             * VALIDASI STATE
             * =========================
             */
            if ($offer->status !== 'in_review') {
                abort(400, 'Penawaran tidak dalam tahap persetujuan pelanggan');
            }

            $currentReview = $offer->currentReview;

            if (!$currentReview || $currentReview->reviewStep->code !== 'customer') {
                abort(403, 'Penawaran belum masuk tahap persetujuan pelanggan');
            }

            /*
             * =========================
             * UPDATE REVIEW CUSTOMER
             * =========================
             */
            $currentReview->update([
                'decision' => $validated['decision'],
                'note' => $validated['note'] ?? null,
                'reviewer_id' => $customer->id,
                'reviewed_at' => now(),
            ]);

            /*
             * =========================
             * CUSTOMER APPROVE → FINAL
             * =========================
             */
            if ($validated['decision'] === 'approved') {
                $offer->update([
                    'status' => 'approved',
                ]);

                $data = app(InvoiceService::class)->createFromOffer($offer);

                return response()->json($data);
            }

            /*
             * =========================
             * CUSTOMER REJECT → KEMBALI KE ADMIN Penawaran
             * =========================
             */
            $offer->update([
                'status' => 'rejected',
            ]);

            // $adminPenawaranStep = ReviewStep::where('code', 'admin_penawaran')->firstOrFail();

            // OfferReview::create([
            //     'offer_id' => $offer->id,
            //     'review_step_id' => $adminPenawaranStep->id,
            //     'decision' => 'pending',
            // ]);

            return response()->json([
                'message' => 'Penawaran ditolak pelanggan dan dikembalikan ke Admin Penawaran',
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

    public function updateDraft(StoreCustomerOfferRequest $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $customerAccount = auth('customer')->user();

            $offer = Offer::query()
                ->where('id', $id)
                ->where('customer_id', $customerAccount->customer_id)
                ->where('status', 'draft')
                ->firstOrFail();

            /*
             * UPDATE OFFER (DRAFT ONLY)
             */
            $offer->update([
                'title' => $request->title,
                'offer_date' => $request->offer_date,
                'expired_date' => $request->expired_date,
                'request_number' => $request->request_number,
                'location' => $request->location,
                'status' => 'in_review',
                'template_id' => $request->template_id,
                'additional_description' => $request->additional_description,
            ]);

            foreach ($request->samples as $sampleInput) {
                $sample = $offer->samples()->create([
                    'title' => $sampleInput['title'],
                ]);

                foreach ($sampleInput['parameters'] as $param) {
                    $sample->parameters()->create([
                        'test_parameter_id' => $param['test_parameter_id'],
                        'price' => $param['unit_price'],
                        'qty' => $param['qty'],
                        'subkon_id' => 1, // default internal
                    ]);
                }
            }

            $firstStep = ReviewStep::where('code', 'admin_penawaran')->firstOrFail();

            OfferReview::create([
                'offer_id' => $offer->id,
                'review_step_id' => $firstStep->id,
                'decision' => 'pending',
            ]);

            return response()->json([
                'message' => 'Draft penawaran berhasil diperbarui',
                'offer_id' => $offer->id,
            ], 200);
        });
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
}
