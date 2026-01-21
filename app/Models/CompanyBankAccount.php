<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyBankAccount extends Model
{
    protected $guarded = [];

    public function invoicePayment()
    {
        return $this->hasMany(InvoicePayment::class);
    }
}
