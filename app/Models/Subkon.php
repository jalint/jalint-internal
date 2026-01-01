<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subkon extends Model
{
    protected $guarded = [];

    public function offerDetail()
    {
        return $this->belongsTo(OfferDetail::class);
    }
}
