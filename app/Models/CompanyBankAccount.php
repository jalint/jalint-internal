<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyBankAccount extends Model
{
    protected $guarded = [];

    public function invoicePayments()
    {
        return $this->hasMany(InvoicePayment::class);
    }
}
