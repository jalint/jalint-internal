<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferSample extends Model
{
    protected $guarded = [];

    /* =======================
     | RELATIONS
     ======================= */

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    // Satu contoh uji → banyak parameter
    public function parameters()
    {
        return $this->hasMany(OfferSampleParameter::class);
    }

    /* =======================
     | CALCULATED
     ======================= */

    public function getSubtotalAttribute()
    {
        return $this->parameters->sum(fn ($p) => $p->unit_price * $p->qty);
    }
}
