<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LhpDocument extends Model
{
    protected $guarded = [];

    /* =======================
     | RELATIONS
     ======================= */

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function details()
    {
        return $this->hasMany(LhpDocumentDetail::class);
    }

    public function reviews()
    {
        return $this->hasMany(LhpReview::class);
    }

    // review yang sedang aktif
    public function currentReview()
    {
        return $this->hasOne(LhpReview::class)
            ->where('decision', 'pending');
    }

    public function latestRevisedReview()
    {
        return $this->hasOne(LhpReview::class)
            ->where('decision', 'revised')
            ->latestOfMany();
    }

    /* =======================
     | WORKFLOW HELPERS
     ======================= */

    public function isWaitingFor(string $role): bool
    {
        return $this->current_review_role === $role
            && optional($this->currentReview)->decision === 'pending';
    }

    public function markInReview(string $nextRole)
    {
        $this->update([
            'status' => 'in_review',
            'current_review_role' => $nextRole,
        ]);
    }

    public function markRevised(string $backToRole)
    {
        $this->update([
            'status' => 'revised',
            'current_review_role' => $backToRole,
        ]);
    }

    public function markValidated()
    {
        $this->update([
            'status' => 'validated',
            'current_review_role' => null,
        ]);
    }
}
