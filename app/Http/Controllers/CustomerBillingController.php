<?php

namespace App\Http\Controllers;

use App\Models\LhpDocument;
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
            // 1. SELECT UTAMA (OFFERS)
            // Pastikan 'customer_id' terpilih agar relasi 'customer' jalan
            // Pastikan 'created_at' terpilih agar 'orderByDesc' valid
            // Pastikan 'is_dp' terpilih jika logic di frontend butuh info DP
            ->select([
                'offers.id',
                'offers.customer_id',
                'offers.title',
                'offers.offer_number',
                'offers.offer_date',
                'offers.expired_date',
                'offers.created_at',
                'offers.is_dp',
                'offers.status',
            ])
            ->where('customer_id', $customer->customer_id)
            ->where('status', 'completed')
            ->whereHas('invoice')
            // 2. SELECT PADA JOIN/RELASI (Eager Loading)
            ->with([
                // Ambil field invoice seperlunya.
                // 'offer_id' WAJIB agar bisa nyambung ke parent offer.
                // 'total_amount' WAJIB untuk logika perhitungan di bawah.
                'invoice:id,offer_id,total_amount,status',

                // Ambil field payment seperlunya.
                // 'invoice_id' WAJIB agar bisa nyambung ke parent invoice.
                'invoice.payments:id,invoice_id,amount,status',

                // Customer (sudah oke)
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

        if ($request->filled('search')) {
            $search = $request->search;

            // Menggunakan grouping (tanda kurung) agar logic OR tidak merusak filter Customer ID
            $query->where(function ($q) use ($search) {
                $q->where('offers.title', 'like', "%{$search}%")
                  ->orWhere('offers.offer_number', 'like', "%{$search}%");
            });
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

    public function show($id)
    {
        // 1. Ambil User Login
        $customer = auth('customer')->user();

        // 2. Query Data Offer Dasar
        // Kita load relasi standar dulu (invoice & payments),
        // TAPI lhpDocument JANGAN di-load dulu.
        $offer = Offer::with([
            'customer:id,name,address',
            'customer.customerContact',
            // 'samples',
            'invoice.payments',
        ])
            ->where('customer_id', $customer->customer_id) // Pastikan milik customer ybs
            ->findOrFail($id);

        // 3. Logika Perhitungan Pembayaran
        $invoice = $offer->invoice;

        // Default values jika invoice belum dibuat admin
        $totalTagihan = 0;
        $totalTerbayar = 0;
        $sisaTagihan = 0;
        $isPaid = false;

        if ($invoice) {
            $totalTagihan = $invoice->total_amount;

            // Hitung total yang statusnya 'approved'
            $totalTerbayar = $invoice->payments
                ->where('status', 'approved')
                ->sum('amount');

            // Hitung Sisa (Gunakan max(0) agar tidak minus jika ada kelebihan bayar dikit)
            $sisaTagihan = max(0, $totalTagihan - $totalTerbayar);

            // Tentukan status Lunas
            // Dianggap lunas jika total terbayar >= total tagihan
            $isPaid = $totalTerbayar >= $totalTagihan;
        }

        // 4. KONDISIONAL: Load LHP Document
        // Jika lunas ($isPaid == true), baru kita ambil data LHP
        if ($isPaid) {
            $lhpDocument = LhpDocument::query()
               ->with([
                   'details',
                   'details.lhpDocumentParamters',
                   'details.lhpDocumentParamters.offerSampleParameter',
                   'details.lhpDocumentParamters.offerSampleParameter.testParameter:id,test_method_id,name',
                   'details.lhpDocumentParamters.offerSampleParameter.testParameter.testMethod:id,name',
               ])
            ->where('offer_id', $offer->id)->get();
        }

        // 5. Format Data Tambahan (Append ke object Offer)
        // Supaya frontend enak bacanya, kita buat property baru 'payment_summary'
        $offer->payment_summary = [
            'invoice_created' => $invoice ? true : false,
            'status' => $isPaid ? 'Lunas' : 'Belum Lunas',
            'total_bill' => $totalTagihan,      // Total yang harus dibayar
            'total_paid' => $totalTerbayar,     // Sudah dibayar
            'remaining_bill' => $sisaTagihan,       // Sisa tagihan
        ];

        return response()->json(['offer' => $offer, 'lhp_documents' => $lhpDocument]);
    }
}
