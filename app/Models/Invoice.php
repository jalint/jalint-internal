<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = [];

    public function details()
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }
}
