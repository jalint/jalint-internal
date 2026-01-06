<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskLetter extends Model
{
    protected $guarded = [];

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function officers()
    {
        return $this->hasMany(TaskLetterOfficer::class);
    }
}
