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
    public function summary()
    {
        $customerId = auth('customer')->user()->customer_id;

        $counts = Offer::where('customer_id', $customerId)
            ->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as waiting,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        ")
            ->first();

        return response()->json([
            'all' => (int) $counts->total,
            'draft' => (int) $counts->draft,
            'waiting' => (int) $counts->waiting,
            'approved' => (int) $counts->approved,
            'rejected' => (int) $counts->rejected,
        ]);
    }

    public function index(Request $request)
    {
        $customerId = auth('customer')->user()->customer_id;

        $query = Offer::query()->select(['id', 'offer_number', 'title', 'offer_date', 'expired_date', 'status', 'customer_id'])
            ->where('customer_id', $customerId);

        switch ($request->filter) {
            case 'draft':
                $query->where('status', 'draft');
                break;

            case 'waiting': // menunggu review admin
                $query->where('status', 'in_review');
                break;

            case 'approved':
                $query->where('status', 'approved');
                break;

            case 'rejected':
                $query->where('status', 'rejected');
                break;

            case 'all':
            default:
                // tidak perlu apa-apa
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

        $offers = $query->with('customer:id,name')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ? $request->per_page : 15);

        return response()->json($offers);
    }

    public function store(StoreCustomerOfferRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $customerAccount = auth('customer')->user();
            $customerId = $customerAccount->customer_id;

            /**
             * 1️⃣ CREATE OFFER.
             */
            $offer = Offer::create([
                'offer_number' => $this->generateOfferNumber(),
                'title' => $request->title,
                'customer_id' => $customerId,
                'offer_date' => $request->offer_date,
                'expired_date' => $request->expired_date,
                'request_number' => $request->request_number,
                'location' => $request->location,
                'status' => 'in_review',
                'created_by_id' => $customerAccount->id,
                'created_by_type' => 'customer',
            ]);

            /*
             * 2️⃣ CREATE SAMPLES + PARAMETERS
             */
            foreach ($request->samples as $sampleInput) {
                $sample = $offer->samples()->create([
                    'title' => $sampleInput['title'],
                ]);

                foreach ($sampleInput['parameters'] as $param) {
                    $sample->parameters()->create([
                        'test_parameter_id' => $param['test_parameter_id'],
                        'price' => $param['price'],
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
                'location' => $offer->location,
                'subtotal_amount' => $offer->subtotal_amount,
                'vat_amount' => $offer->ppn_amount,
                'withholding_tax_amount' => $offer->pph_amount,
                'total_amount' => $offer->total_amount,
                'payable_amount' => $offer->payable_amount,
                'template' => $offer->template,
                'documents' => $offer->documents,
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
