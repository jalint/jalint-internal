<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferDetail extends Model
{
    protected $guarded = [];

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
        return $this->hasOne(Subkon::class);
    }
}
