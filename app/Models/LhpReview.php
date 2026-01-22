<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LhpReview extends Model
{
    protected $guarded = [];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /* =======================
     | RELATIONS
     ======================= */

    public function lhp()
    {
        return $this->belongsTo(LhpDocument::class, 'lhp_document_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviewStep()
    {
        return $this->belongsTo(LhpStep::class);
    }

    /* =======================
     | HELPERS
     ======================= */

    public function approve(int $userId)
    {
        $this->update([
            'decision' => 'approved',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);
    }

    public function revise(int $userId, ?string $note = null)
    {
        $this->update([
            'decision' => 'revised',
            'note' => $note,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);
    }
}
