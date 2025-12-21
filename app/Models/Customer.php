<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = [];

    public function customerType()
    {
        return $this->belongsTo(CustomerType::class);
    }

    public function customerAccount()
    {
        return $this->belongsTo(CustomerAccount::class);
    }
}
