<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fppcu extends Model
{
    protected $table = 'fppcu';

    public function lhp()
    {
        return $this->belongsTo(LhpDocument::class, 'lhp_document_id');
    }

    public function fppcuParameters()
    {
        return $this->hasMany(FppcuParameter::class);
    }
}
