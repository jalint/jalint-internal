<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Offer;
use Illuminate\Support\Carbon;

class InvoiceService
{
    public function createFromOffer(Offer $offer): Invoice
    {
        if ($offer->invoice) {
            return $offer->invoice; // idempotent
        }

        $subTotal = $offer->subtotal_amount - $offer->discount_amount;

        $vatAmount = $subTotal * ($offer->vat_percent / 100);
        $pphAmount = $subTotal * ($offer->pph_percent / 100);

        $invoice = Invoice::create([
            'offer_id' => $offer->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'customer_id' => $offer->customer_id,
            'issued_at' => now(),
            'due_date' => now()->addDays(14),
            'status' => 'unpaid',
            'subtotal_amount' => $offer->subtotal_amount, // Sebelum Diskon
            'total_amount' => $offer->total_amount,
            'discount_amount' => $offer->discount_amount,
            'vat_amount' => $vatAmount,
            'vat_percent' => $offer->vat_percent,
            'pph_amount' => $pphAmount,
            'pph_percent' => $offer->pph_percent,
        ]);

        foreach ($offer->samples as $sample) {
            foreach ($sample->parameters as $param) {
                $invoice->details()->create([
                    // 'test_parameter_id' => $param->test_parameter_id,
                    'parameter_name' => $param->testParameter->name,
                    'unit_price' => $param->price,
                    'qty' => $param->qty,
                    'total_price' => $param->price * $param->qty,
                ]);
            }
        }

        return $invoice;
    }

    protected function generateInvoiceNumber(): string
    {
        $date = Carbon::now()->format('Ymd');

        // Ambil invoice terakhir hari ini
        $lastInvoice = Invoice::whereDate('created_at', Carbon::today())
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $nextNumber = 1;

        if ($lastInvoice) {
            // ambil sequence terakhir
            $lastSequence = (int) substr($lastInvoice->invoice_number, -4);
            $nextNumber = $lastSequence + 1;
        }

        return sprintf(
            'INV/%s/%04d',
            $date,
            $nextNumber
        );
    }
}
