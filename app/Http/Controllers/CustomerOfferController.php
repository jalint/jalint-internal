<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerOfferRequest;
use App\Models\Offer;
use App\Models\OfferReview;
use App\Models\ReviewStep;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $query = Offer::query()->select(['id', 'offer_number', 'title', 'offer_date', 'expired_date', 'status'])
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

        $offers = $query
            ->orderByDesc('created_at')
            ->paginate($request->per_page ? $request->per_page : 15);

        return response()->json($offers);
    }

    public function store(StoreCustomerOfferRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $customerId = auth('customer')->id();

                $offer = Offer::create([
                    'offer_number' => $this->generateOfferNumber(),
                    'title' => $request->title,
                    'customer_id' => $customerId,
                    'offer_date' => $request->offer_date,
                    'expired_date' => $request->expired_date,
                    'request_number' => $request->request_number,
                    'status' => 'in_review',
                    'created_by_id' => $customerId,
                    'created_by_type' => 'customer',
                ]);

                foreach ($request->details as $detail) {
                    $offer->details()->create([
                        'test_parameter_id' => $detail['test_parameter_id'],
                        'price' => $detail['price'],
                        'qty' => $detail['qty'],
                        'subkon_id' => 1, // Default JLI
                    ]);
                }

                $firstStep = ReviewStep::where('code', 'admin_penawaran')->firstOrFail();

                OfferReview::create([
                    'offer_id' => $offer->id,
                    'review_step_id' => $firstStep->id,
                    'reviewer_id' => null,
                    'decision' => 'pending',
                ]);

                return response()->json($offer);
            });
        } catch (\Throwable $th) {
            Log::error('Failed to create customer offer', ['error' => $th->getMessage(), 'line' => $th->getLine(), 'code' => $th->getCode()]);

            return response()->json(['message' => 'Terjadi Kesalahan'], 500);
        }
    }

    public function show($id)
    {
        $customerId = auth('customer')->user()->customer_id;
        // dd($customerId);
        $offer = Offer::with([
            'template',
            'details.testParameter.testPackages.regulation',
            'details.testPackage.regulation',
        ])
        ->where('customer_id', $customerId)
        ->findOrFail($id);

        /*
         |-------------------------------------------------
         | GROUP DETAIL BY TEST PACKAGE (UI FRIENDLY)
         |-------------------------------------------------
         */
        $groupedDetails = $offer->details
            ->groupBy(function ($detail) {
                return optional($detail->testPackage)->id
                    ?? optional($detail->testParameter->testPackages->first())->id;
            })
            ->map(function ($items) {
                $first = $items->first();

                $package =
                    $first->testPackage
                    ?? $first->testParameter->testPackages->first();

                return [
                    'test_package' => $package ? [
                        'id' => $package->id,
                        'name' => $package->name,
                        'regulation' => $package->regulation,
                    ] : null,

                    'parameters' => $items->map(function ($detail) {
                        return [
                            'id' => $detail->testParameter->id,
                            'name' => $detail->testParameter->name,
                            'unit' => $detail->testParameter->unit,
                            'price' => $detail->price,
                            'qty' => $detail->qty,
                            'total' => $detail->price * $detail->qty,
                        ];
                    })->values(),
                ];
            })
            ->values();

        /*
         |-------------------------------------------------
         | RESPONSE
         |-------------------------------------------------
         */
        return response()->json([
            'offer' => [
                'id' => $offer->id,
                'offer_number' => $offer->offer_number,
                'title' => $offer->title,
                'offer_date' => $offer->offer_date,
                'expired_date' => $offer->expired_date,
                'status' => $offer->status,
                'additional_description' => $offer->additional_description,
                'location' => $offer->location,
                'subtotal_amount' => $offer->subtotal_amount,
                'vat_amount' => $offer->ppn_amount,
                'withholding_tax_amount' => $offer->pph_amount,
                'total_amount' => $offer->total_amount,
                'payable_amount' => $offer->payable_amount,
                'template' => $offer->template,
            ],
            'details' => $groupedDetails,
        ]);
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
