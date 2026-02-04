<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        $approvedPaidAmount = $invoice->payments()
                            ->where('status', 'approved')
                            ->sum('amount');

        $remainingAmount = max(
            $invoice->total_amount - $approvedPaidAmount,
            0
        );

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
                'proof_file' => $payment->proof_file,
            ],

            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $approvedPaidAmount,
                'remaining_amount' => $remainingAmount,
            ],

            'offer' => [
                'id' => $offer->id,
                'offer_number' => $offer->offer_number,
                'title' => $offer->title,
                'status' => $offer->status,
                'is_dp' => $offer->is_dp,
                'subtotal_amount' => $offer->subtotal_amount,
                'total_amount' => $offer->total_amount,
                'pph_percent' => $offer->pph_percent,
                'pph_amount' => $offer->pph_amount,
                'vat_percent' => $offer->vat_percent,
                'ppn_amount' => $offer->ppn_amount,
                'discount_amount' => $offer->discount_amount,
                'dp_amount' => $offer->dp_amount,
            ],

            'customer' => [
                'name' => $offer->customer->name,
                'contact' => $offer->customer->customerContact,
            ],

            'samples' => $sampleSummary,
        ]);
    }

    public function uploadFakturPajak(Request $request, Invoice $invoice)
    {
        // =========================
        // VALIDASI
        // =========================
        $validated = $request->validate([
            'faktur_pajak' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        DB::beginTransaction();

        try {
            // =========================
            // HAPUS FILE LAMA (jika ada)
            // =========================
            if ($invoice->faktur_pajak_path && Storage::disk('local')->exists($invoice->faktur_pajak_path)) {
                Storage::disk('local')->delete($invoice->faktur_pajak_path);
            }

            // =========================
            // SIMPAN FILE BARU
            // =========================
            $file = $validated['faktur_pajak'];
            $filename = Str::uuid().'.pdf';

            $path = $file->storeAs('faktur-pajak', $filename, 'local');

            // =========================
            // UPDATE INVOICE
            // =========================
            $invoice->update([
                'faktur_pajak_path' => $path,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Faktur pajak berhasil diunggah.',
                'path' => $path,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            // optional: log error
            Log::error('Upload faktur pajak gagal', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal mengunggah faktur pajak.',
            ], 500);
        }
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
