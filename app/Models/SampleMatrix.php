<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SampleMatrix extends Model
{
    protected $table = 'sample_matrices';

    protected $guarded = [];

    public function sampleType()
    {
        return $this->belongsTo(SampleType::class);
    }
}
