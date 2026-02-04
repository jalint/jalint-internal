<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FppcuParameter extends Model
{
    protected $guarded = [];

    public function fppcu()
    {
        return $this->belongsTo(Fppcu::class);
    }
}
