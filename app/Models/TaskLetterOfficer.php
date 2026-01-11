<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskLetterOfficer extends Model
{
    protected $fillable = [
        'task_letter_id', 'employee_id', 'tast_letter_id',
        'position', 'description',
    ];

    public function taskLetter()
    {
        return $this->belongsTo(TaskLetter::class);
    }
}
