<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LhpStep extends Model
{
    protected $guarded = [];

    public function reviews()
    {
        return $this->hasMany(LhpReview::class);
    }
}
