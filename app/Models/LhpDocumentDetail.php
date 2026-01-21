<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LhpDocumentDetail extends Model
{
    protected $guarded = [];

    public function lhp()
    {
        return $this->belongsTo(LhpDocument::class, 'lhp_document_id');
    }

    public function lhpDocumentParamters()
    {
        return $this->hasMany(LhpDocumentParameter::class);
    }
}
