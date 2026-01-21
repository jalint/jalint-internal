<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LhpDocumentParameter extends Model
{
    protected $guarded = [];

    public function LhpDocumentDetail()
    {
        return $this->belongsTo(LhpDocumentDetail::class);
    }

    public function offerSampleParameter()
    {
        return $this->belongsTo(OfferSampleParameter::class);
    }
}
