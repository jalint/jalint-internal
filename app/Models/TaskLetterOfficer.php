<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskLetterOfficer extends Model
{
    protected $guarded = [];

    public function taskLetter()
    {
        return $this->belongsTo(TaskLetter::class);
    }
}
