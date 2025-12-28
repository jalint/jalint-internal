<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $guarded = [];

    public function reviews()
    {
        return $this->hasMany(OfferReview::class);
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
}
