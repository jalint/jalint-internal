<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferSampleParameter extends Model
{
    protected $guarded = [];

    /* =======================
     | RELATIONS
     ======================= */

    public function sample()
    {
        return $this->belongsTo(OfferSample::class, 'offer_sample_id');
    }

    public function testParameter()
    {
        return $this->belongsTo(TestParameter::class);
    }

    public function testPackage()
    {
        return $this->belongsTo(TestPackage::class);
    }

    public function subkon()
    {
        return $this->belongsTo(Subkon::class);
    }

    /* =======================
     | CALCULATED
     ======================= */

    public function getSubtotalAttribute()
    {
        return $this->unit_price * $this->qty;
    }
}
