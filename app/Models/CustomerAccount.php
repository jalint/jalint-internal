<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class CustomerAccount extends Authenticatable
{
    use HasApiTokens;
    use HasRoles;

    protected $guarded = [];

    protected $guard_name = 'customer';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
