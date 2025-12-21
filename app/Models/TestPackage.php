<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestPackage extends Model
{
    protected $guarded = [];

    public function testParameters()
    {
        return $this->belongsToMany(
            TestParameter::class,
            'parameter_test_package'
        );
    }

    public function sampleMatrix()
    {
        return $this->belongsTo(SampleMatrix::class);
    }

    public function regulation()
    {
        return $this->belongsTo(Regulation::class);
    }
}
