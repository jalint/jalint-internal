<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::query()
            ->with([
                'offer:id,offer_number,customer_id',
                'offer.customer:id,name',
                'payments' => fn ($q) => $q->latest(),
            ])
            ->orderByDesc('created_at');

        // =========================
        // FILTER
        // =========================
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->whereHas('payments', function ($q) use ($request) {
                $q->where('status', $request->payment_status);
            });
        }

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('offer', fn ($oq) => $oq->where('offer_number', 'like', "%{$search}%")
                  );
            });
        }

        return response()->json(
            $query->paginate($request->per_page ?? 15)
        );
    }

    public function show($id)
    {
        $invoice = Invoice::with([
            'offer.customer',
            'details',
            'payments',
            'companyBankAccount',
        ])->findOrFail($id);

        return response()->json($invoice);
    }

    public function storePayment(Request $request, int $invoiceId)
    {
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'string'],
            'company_bank_account_id' => ['required', 'exists:company_bank_accounts,id'],
            'reference_number' => ['nullable', 'string'],
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'note' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $invoiceId, $request) {
            /** @var Invoice $invoice */
            $invoice = Invoice::lockForUpdate()->findOrFail($invoiceId);

            if ($invoice->status === 'paid') {
                abort(400, 'Invoice sudah lunas');
            }

            // =========================
            // HITUNG SISA TAGIHAN
            // =========================
            $alreadyPaid = $invoice->payments()
                ->where('status', 'approved')
                ->sum('amount');

            $remaining = $invoice->total_amount - $alreadyPaid;

            if ($validated['amount'] > $remaining) {
                abort(400, 'Jumlah pembayaran melebihi sisa tagihan');
            }

            // =========================
            // UPLOAD BUKTI BAYAR
            // =========================
            $file = $request->file('proof_file');

            $path = $file->store('invoice-payments', 'public');

            // =========================
            // SIMPAN PEMBAYARAN
            // =========================
            $payment = $invoice->payments()->create([
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'company_bank_account_id' => $validated['company_bank_account_id'],
                'reference_number' => $validated['reference_number'],
                'note' => $validated['note'],
                'proof_file' => $path,
                'status' => 'approved', // admin langsung approve
                'created_by' => auth()->id(),
            ]);

            // =========================
            // UPDATE STATUS INVOICE
            // =========================
            $totalPaid = $alreadyPaid + $validated['amount'];

            if ($totalPaid >= $invoice->total_amount) {
                $invoice->update(['status' => 'paid']);
            } else {
                $invoice->update(['status' => 'partial']);
            }

            return response()->json([
                'message' => 'Pembayaran berhasil dicatat',
                'invoice_status' => $invoice->status,
                'total_paid' => $totalPaid,
                'remaining' => max(0, $invoice->total_amount - $totalPaid),
            ]);
        });
    }

    public function reviewPayment(Request $request, $paymentId)
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'note' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $paymentId) {
            $payment = InvoicePayment::lockForUpdate()->findOrFail($paymentId);
            $invoice = $payment->invoice;

            $payment->update([
                'status' => $validated['decision'],
                // 'note' => $validated['note'],
                'validated_by' => auth()->id(),
                'validated_at' => now(),
            ]);

            if ($validated['decision'] === 'approved') {
                $totalPaid = $invoice->payments()
                    ->where('status', 'approved')
                    ->sum('amount');

                if ($totalPaid >= $invoice->total_amount) {
                    $invoice->update(['status' => 'paid']);
                } else {
                    $invoice->update(['status' => 'partial']);
                }
            }

            return response()->json([
                'message' => 'Pembayaran berhasil direview',
            ]);
        });
    }

    public function storeCustomerPayment(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'company_bank_account_id' => [
                'required',
                'exists:company_bank_accounts,id',
            ],
        ]);

        return DB::transaction(function () use ($request) {
            $customer = auth('customer')->user();

            /** @var Invoice $invoice */
            $invoice = Invoice::lockForUpdate()
                ->where('customer_id', $customer->customer_id)
                ->with([
                    'offer',
                    'payments' => fn ($q) => $q->where('status', 'approved'),
                ])
                ->findOrFail($request->invoice_id);

            $offer = $invoice->offer;

            /*
            |--------------------------------------------------------------------------
            | VALIDASI STATUS INVOICE
            |--------------------------------------------------------------------------
            */
            if (in_array($invoice->status, ['paid', 'cancelled'], true)) {
                abort(400, 'Invoice tidak dapat dibayar');
            }

            /*
            |--------------------------------------------------------------------------
            | HITUNG PAYMENT APPROVED
            |--------------------------------------------------------------------------
            */
            $approvedPayments = $invoice->payments;
            $approvedCount = $approvedPayments->count();
            $approvedAmount = $approvedPayments->sum('amount');

            /*
            |--------------------------------------------------------------------------
            | TENTUKAN AMOUNT (SINGLE SOURCE OF TRUTH)
            |--------------------------------------------------------------------------
            */
            if ((int) $offer->is_dp === 0) {
                // NON DP → langsung lunas
                if ($approvedCount >= 1) {
                    abort(400, 'Pelunasan sudah dilakukan');
                }

                $amount = $invoice->total_amount;
            } else {
                // DP FLOW
                if ($approvedCount === 0) {
                    // PAYMENT PERTAMA → DP
                    if ((float) $offer->dp_amount <= 0) {
                        abort(400, 'DP amount tidak valid');
                    }

                    $amount = $offer->dp_amount;
                } elseif ($approvedCount === 1) {
                    // PAYMENT KEDUA → PELUNASAN
                    $remaining = $invoice->total_amount - $approvedAmount;

                    if ($remaining <= 0) {
                        abort(400, 'Sisa pembayaran tidak valid');
                    }

                    $amount = $remaining;
                } else {
                    abort(400, 'Seluruh pembayaran sudah dilakukan');
                }
            }

            /*
            |--------------------------------------------------------------------------
            | SIMPAN BUKTI PEMBAYARAN
            |--------------------------------------------------------------------------
            */
            $path = $request->file('proof_file')
                ->store('invoice-payments', 'public');

            $invoice->payments()->create([
                'payment_date' => now(),
                'amount' => $amount,
                'proof_file' => $path,
                'company_bank_account_id' => $request->company_bank_account_id,
                'status' => 'pending',
            ]);

            /*
            |--------------------------------------------------------------------------
            | RESPONSE MESSAGE
            |--------------------------------------------------------------------------
            */
            $message = 'Bukti pembayaran berhasil dikirim dan menunggu verifikasi';

            if ((int) $offer->is_dp === 1 && $approvedCount === 0) {
                $message = 'Bukti pembayaran DP berhasil dikirim dan menunggu verifikasi';
            }

            if ((int) $offer->is_dp === 1 && $approvedCount === 1) {
                $message = 'Bukti pembayaran pelunasan berhasil dikirim dan menunggu verifikasi';
            }

            return response()->json([
                'message' => $message,
            ]);
        });
    }
}
