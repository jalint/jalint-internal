<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestParameter extends Model
{
    protected $guarded = [];

    public function testMethod()
    {
        return $this->belongsTo(TestMethod::class);
    }

    public function testPackages()
    {
        return $this->belongsToMany(
            TestPackage::class,
            'parameter_test_package'
        );
    }

    public function sampleType()
    {
        return $this->belongsTo(SampleType::class);
    }
}
