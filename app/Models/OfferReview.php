<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferReview extends Model
{
    protected $guarded = [];

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function reviewStep()
    {
        return $this->belongsTo(ReviewStep::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
