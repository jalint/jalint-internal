<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $guarded = [];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // helper (opsional tapi sangat berguna)
    public function currentReview()
    {
        return $this->hasOne(OfferReview::class)
            ->where('decision', 'pending');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details()
    {
        return $this->hasMany(OfferDetail::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    /* =======================
     | RELATIONS
     ======================= */

    // Satu penawaran → banyak contoh uji
    public function samples()
    {
        return $this->hasMany(OfferSample::class);
    }

    // Review workflow
    public function reviews()
    {
        return $this->hasMany(OfferReview::class);
    }

    // public function currentReview()
    // {
    //     return $this->hasOne(OfferReview::class)
    //         ->where('decision', 'pending')
    //         ->latestOfMany();
    // }

    /* =======================
     | CALCULATED ATTRIBUTES
     ======================= */

    // public function getSubtotalAmountAttribute()
    // {
    //     return $this->samples
    //         ->flatMap->parameters
    //         ->sum(fn ($p) => $p->unit_price * $p->qty);
    // }

    // public function getVatAmountAttribute()
    // {
    //     return $this->subtotal_amount * ($this->vat_percent / 100);
    // }

    // public function getWithholdingTaxAmountAttribute()
    // {
    //     return $this->subtotal_amount * ($this->withholding_tax_percent / 100);
    // }

    // public function getTotalAmountAttribute()
    // {
    //     return $this->subtotal_amount
    //         - $this->discount_amount
    //         + $this->vat_amount
    //         - $this->withholding_tax_amount;
    // }

    public function taskLetter()
    {
        return $this->hasOne(TaskLetter::class);
    }

    // di Offer model
    public function hasApprovedBy(string $role): bool
    {
        return $this->reviews()
            ->where('reviewer_role', $role)
            ->where('decision', 'approved')
            ->exists();
    }

    public function documents()
    {
        return $this->hasMany(OfferDocument::class);
    }

    /**
     * Helper spesifik subkon.
     */
    public function subkonLetterFromAdmin()
    {
        return $this->documents()
            ->where('type', 'subkon_letter')
            ->where('uploaded_by_role', 'admin_kuptdk');
    }

    public function subkonLetterFromCustomer()
    {
        return $this->documents()
            ->where('type', 'subkon_letter')
            ->where('uploaded_by_role', 'customer');
    }
}
