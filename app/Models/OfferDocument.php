<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferDocument extends Model
{
    protected $guarded = [];

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }
}
