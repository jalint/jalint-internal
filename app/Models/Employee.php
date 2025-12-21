<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $guarded = [];

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function certifications()
    {
        return $this->belongsToMany(
            Certification::class,
            'certification_employee'
        );
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path
            ? asset('storage/'.$this->photo_path)
            : null;
    }
}
