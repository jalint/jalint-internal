<?php

namespace App\Queries;

use App\Models\Offer;

class CustomerOfferStatusResolver
{
    public static function resolve(Offer $offer): string
    {
        return match ($offer->status) {
            'draft' => 'Draft',
            'in_review' => 'Penawaran Diproses',
            'approved' => 'Disetujui',
            'completed' => 'Selesai',
            'rejected' => 'Direvisi',
            default => ucfirst($offer->status),
        };
    }
}
