<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SampleType extends Model
{
    protected $guarded = [];

    // public function regulation()
    // {
    //     return $this->belongsTo(Regulation::class);
    // }

    public function testParameters()
    {
        return $this->hasMany(TestParameter::class);
    }
}
