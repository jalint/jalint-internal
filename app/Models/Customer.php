<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'customer_type_id',
        'city',
        'province',
        'email',
        'website',
        'npwp',
        'postal_code',
        'status',
        'address',
        'customer_account_id',
    ];

    public function customerType()
    {
        return $this->belongsTo(CustomerType::class);
    }

    public function customerAccount()
    {
        return $this->belongsTo(CustomerAccount::class);
    }

    public function customerContact()
    {
        return $this->hasOne(CustomerContact::class);
    }
}
