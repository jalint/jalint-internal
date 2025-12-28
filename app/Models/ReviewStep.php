<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewStep extends Model
{
    protected $guarded = [];

    public function reviews()
    {
        return $this->hasMany(OfferReview::class);
    }
}
