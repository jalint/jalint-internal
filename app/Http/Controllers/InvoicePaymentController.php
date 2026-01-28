<?php

namespace App\Http\Controllers;

use App\Models\InvoicePayment;
use App\Models\Offer;
use Illuminate\Http\Request;

class InvoicePaymentController extends Controller
{
    /**
     * =========================
     * INDEX TAGIHAN
     * =========================.
     */
    public function index(Request $request)
    {
        $request->validate([
            'filter' => ['nullable', 'in:all,verifikasi_pembayaran,pembayaran_disetujui,pembayaran_ditolak'],
            'per_page' => ['nullable', 'integer'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | BASE QUERY
        |--------------------------------------------------------------------------
        | Tagihan = 1 payment
        */
        $query = InvoicePayment::query()
            ->with([
                'invoice.offer:id,offer_number,title,offer_date,is_dp,total_amount',
            ])
            ->orderByDesc('created_at');

        /*
        |--------------------------------------------------------------------------
        | FILTER STATUS
        |--------------------------------------------------------------------------
        */
        switch ($request->filter) {
            case 'verifikasi_pembayaran':
                $query->where('status', 'pending');
                break;

            case 'pembayaran_disetujui':
                $query->where('status', 'approved');
                break;

            case 'pembayaran_ditolak':
                $query->where('status', 'rejected');
                break;

            case 'all':
            default:
                // no filter
                break;
        }

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */
        $payments = $query->paginate($request->per_page ?? 15);

        /*
        |--------------------------------------------------------------------------
        | TRANSFORM DISPLAY
        |--------------------------------------------------------------------------
        */
        $payments->getCollection()->transform(function ($payment) {
            $offer = $payment->invoice->offer;

            /*
            |-----------------------------------------
            | DISPLAY STATUS
            |-----------------------------------------
            */
            $payment->display_status = match ($payment->status) {
                'pending' => 'Verifikasi Pembayaran',
                'approved' => 'Pembayaran Disetujui',
                'rejected' => 'Pembayaran Ditolak',
                default => ucfirst($payment->status),
            };

            /*
            |-----------------------------------------
            | TIPE PEMBAYARAN
            |-----------------------------------------
            | DP atau Pelunasan
            */
            if ($offer->is_dp) {
                $payment->payment_type =
                    $payment->amount < $offer->total_amount
                        ? 'DP'
                        : 'Pelunasan';
            } else {
                $payment->payment_type = 'Pelunasan';
            }

            /*
            |-----------------------------------------
            | DATA DISPLAY
            |-----------------------------------------
            */
            $payment->offer_number = $offer->offer_number;
            $payment->offer_date = $offer->offer_date;
            $payment->offer_title = $offer->title;

            return $payment;
        });

        return response()->json($payments);
    }

    /**
     * =========================
     * SUMMARY TAGIHAN
     * =========================.
     */
    public function summary()
    {
        $base = InvoicePayment::query();

        return response()->json([
            'all' => (clone $base)->count(),

            'verifikasi_pembayaran' => (clone $base)
                ->where('status', 'pending')
                ->count(),

            'pembayaran_disetujui' => (clone $base)
                ->where('status', 'approved')
                ->count(),

            'pembayaran_ditolak' => (clone $base)
                ->where('status', 'rejected')
                ->count(),
        ]);
    }

    public function show($paymentId)
    {
        $payment = InvoicePayment::with([
            'invoice.offer.customer.customerContact',
            'invoice.offer.samples.parameters.subkon',
            'invoice.offer.samples.parameters.testParameter.sampleType',
            'invoice.offer.documents',
        ])->findOrFail($paymentId);

        $invoice = $payment->invoice;
        $offer = $invoice->offer;

        /*
        |--------------------------------------------------------------------------
        | SAMPLE SUMMARY (Helper)
        |--------------------------------------------------------------------------
        */
        $sampleSummary = $this->summarize($offer);

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'payment_date' => $payment->payment_date,
            ],

            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => $invoice->total_amount,
            ],

            'offer' => [
                'id' => $offer->id,
                'offer_number' => $offer->offer_number,
                'title' => $offer->title,
                'status' => $offer->status,
                'is_dp' => $offer->is_dp,
            ],

            'customer' => [
                'name' => $offer->customer->name,
                'contact' => $offer->customer->customerContact,
            ],

            'samples' => $sampleSummary,
        ]);
    }

    private function summarize(Offer $offer): array
    {
        return $offer->samples->map(function ($sample) {
            $parameters = $sample->parameters;

            /*
            |--------------------------------------------------------------------------
            | REGULASI
            |--------------------------------------------------------------------------
            | Ambil dari sampleType pertama (diasumsikan konsisten)
            */
            $sampleType = optional(
                $parameters->first()?->testParameter
            )->sampleType;

            /*
            |--------------------------------------------------------------------------
            | CONCAT PARAMETER NAME
            |--------------------------------------------------------------------------
            */
            $parameterNames = $parameters
                ->map(fn ($param) => $param->testParameter->name)
                ->unique()
                ->implode(', ');

            /*
            |--------------------------------------------------------------------------
            | TOTAL BIAYA
            |--------------------------------------------------------------------------
            */
            $totalBiaya = $parameters->sum(function ($param) {
                return $param->price * $param->qty;
            });

            return [
                'produk_uji' => $sample->title,
                'regulasi' => $sampleType?->regulation,
                'parameter_uji' => $parameterNames,
                'contoh_uji' => 1,
                'total_biaya' => $totalBiaya,
            ];
        })->values()->toArray();
    }
}
