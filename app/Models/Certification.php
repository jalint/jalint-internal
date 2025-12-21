<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certification extends Model
{
    protected $guarded = [];

    public function testParameters()
    {
        return $this->belongsToMany(
            TestParameter::class,
            'certification_test_parameter'
        );
    }
}
