<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FppcuParameter extends Model
{
    public function fppcu()
    {
        return $this->belongsTo(Fppcu::class);
    }
}
