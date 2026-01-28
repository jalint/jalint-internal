<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\Request;

class CustomerBillingController extends Controller
{
    public function summary(Request $request)
    {
        $customer = auth('customer')->user();

        $base = Offer::query()
            ->where('customer_id', $customer->customer_id)
            ->where('status', 'completed')
            ->whereHas('invoice', function ($q) {
                $q->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth(),
                ]);
            });

        return response()->json([
            'all' => (clone $base)->count(),

            /*
            |--------------------------------------------------------------------------
            | Menunggu Pembayaran Akhir
            |--------------------------------------------------------------------------
            | - NON DP: belum ada payment sama sekali
            | - DP: sudah ada approved payment tapi belum lunas
            */
            'menunggu_pembayaran_akhir' => (clone $base)
                ->whereHas('invoice', function ($q) {
                    $q->where(function ($qq) {
                        // NON DP → belum ada payment
                        $qq->whereHas('offer', fn ($o) => $o->where('is_dp', 0))
                           ->whereDoesntHave('payments');
                    })->orWhere(function ($qq) {
                        // DP → sudah bayar DP tapi belum lunas
                        $qq->whereHas('offer', fn ($o) => $o->where('is_dp', 1))
                           ->whereHas('payments', fn ($p) => $p->where('status', 'approved'))
                           ->whereRaw('
                           (SELECT COALESCE(SUM(amount),0)
                            FROM invoice_payments
                            WHERE invoice_payments.invoice_id = invoices.id
                            AND status = "approved"
                           ) < invoices.total_amount
                       ');
                    });
                })
                ->count(),

            /*
        |--------------------------------------------------------------------------
        | Verifikasi Pembayaran
        |--------------------------------------------------------------------------
        */
            'verifikasi_pembayaran' => (clone $base)
                ->whereHas('invoice.payments', fn ($p) => $p->where('status', 'pending'))
                ->count(),

            /*
        |--------------------------------------------------------------------------
        | Pembayaran Akhir Disetujui
        |--------------------------------------------------------------------------
        */
            'pembayaran_akhir_disetujui' => (clone $base)
                ->whereHas('invoice', function ($q) {
                    $q->whereRaw('
                    (SELECT COALESCE(SUM(amount),0)
                     FROM invoice_payments
                     WHERE invoice_payments.invoice_id = invoices.id
                     AND status = "approved"
                    ) >= invoices.total_amount
                ');
                })
                ->count(),

            /*
        |--------------------------------------------------------------------------
        | Verifikasi Pembayaran Ditolak
        |--------------------------------------------------------------------------
        */
            'verifikasi_pembayaran_ditolak' => (clone $base)
                ->whereHas('invoice.payments', fn ($p) => $p->where('status', 'rejected'))
                ->count(),
        ]);
    }

    public function index(Request $request)
    {
        $customer = auth('customer')->user();

        $query = Offer::query()
            ->where('customer_id', $customer->customer_id)
            ->where('status', 'completed')
            ->whereHas('invoice', function ($q) {
                $q->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth(),
                ]);
            })
            ->with([
                'invoice.payments',
                'customer:id,name',
            ])
            ->orderByDesc('created_at');

        switch ($request->filter) {
            case 'menunggu_pembayaran_akhir':
                $query->whereHas('invoice', function ($q) {
                    $q->where(function ($qq) {
                        $qq->whereHas('offer', fn ($o) => $o->where('is_dp', 0))
                           ->whereDoesntHave('payments');
                    })->orWhere(function ($qq) {
                        $qq->whereHas('offer', fn ($o) => $o->where('is_dp', 1))
                           ->whereHas('payments', fn ($p) => $p->where('status', 'approved'))
                           ->whereRaw('
                           (SELECT COALESCE(SUM(amount),0)
                            FROM invoice_payments
                            WHERE invoice_payments.invoice_id = invoices.id
                            AND status = "approved"
                           ) < invoices.total_amount
                       ');
                    });
                });
                break;

            case 'verifikasi_pembayaran':
                $query->whereHas('invoice.payments', fn ($p) => $p->where('status', 'pending'));
                break;

            case 'pembayaran_akhir_disetujui':
                $query->whereHas('invoice', function ($q) {
                    $q->whereRaw('
                    (SELECT COALESCE(SUM(amount),0)
                     FROM invoice_payments
                     WHERE invoice_payments.invoice_id = invoices.id
                     AND status = "approved"
                    ) >= invoices.total_amount
                ');
                });
                break;

            case 'verifikasi_pembayaran_ditolak':
                $query->whereHas('invoice.payments', fn ($p) => $p->where('status', 'rejected'));
                break;

            case 'all':
            default:
                break;
        }

        $offers = $query->paginate($request->per_page ?? 15);

        /*
        |--------------------------------------------------------------------------
        | DISPLAY STATUS (sinkron dengan summary)
        |--------------------------------------------------------------------------
        */
        $offers->getCollection()->transform(function ($offer) {
            $invoice = $offer->invoice;

            $approvedAmount = $invoice?->payments
                ->where('status', 'approved')
                ->sum('amount');

            $hasPending = $invoice?->payments
                ->where('status', 'pending')
                ->isNotEmpty();

            $hasRejected = $invoice?->payments
                ->where('status', 'rejected')
                ->isNotEmpty();

            $offer->display_status = match (true) {
                $hasPending => 'Verifikasi Pembayaran',

                $hasRejected => 'Verifikasi Pembayaran Ditolak',

                $approvedAmount >= $invoice->total_amount => 'Pembayaran Akhir Disetujui',

                default => 'Menunggu Pembayaran Akhir',
            };

            return $offer;
        });

        return response()->json($offers);
    }
}
