<?php

namespace App\Queries;

use App\Models\Offer;

class OfferVisibility
{
    public static function forRole(string $role)
    {
        return match ($role) {
            'admin_penawaran' => self::adminPenawaran(),
            'admin_kuptdk' => self::adminKuptdk(),
            'manager_admin' => self::managerAdmin(),
            'manager_teknis' => self::managerTeknis(),
            default => Offer::query()->whereRaw('1=0'),
        };
    }

    /**
     * ADMIN PENAWARAN
     * Semua penawaran adalah domain dia.
     */
    private static function adminPenawaran()
    {
        return Offer::query();
    }

    /**
     * ADMIN KUPTDK
     * Mulai dari kaji ulang teknis ke atas.
     */
    private static function adminKuptdk()
    {
        return Offer::query()
            ->whereIn('status', ['in_review', 'approved', 'completed', 'rejected'])
            ->whereHas('currentReview.reviewStep', function ($q) {
                $q->whereIn('code', [
                    'admin_kuptdk',
                    'manager_admin',
                    'manager_teknis',
                    'customer',
                ]);
            });
    }

    /**
     * MANAGER ADMIN
     * HANYA yang menyentuh MA ke atas.
     */
    private static function managerAdmin()
    {
        return Offer::query()
            ->whereIn('status', ['in_review', 'approved', 'completed', 'rejected'])
            ->whereHas('currentReview.reviewStep', function ($q) {
                $q->whereIn('code', [
                    'manager_admin',
                    'manager_teknis',
                    'customer',
                ]);
            });
    }

    /**
     * MANAGER TEKNIS
     * Fokus teknis & hasil akhir.
     */
    private static function managerTeknis()
    {
        return Offer::query()
            ->whereIn('status', ['in_review', 'approved', 'completed', 'rejected'])
            ->whereHas('currentReview.reviewStep', function ($q) {
                $q->whereIn('code', [
                    'manager_teknis',
                    'customer',
                ]);
            });
    }
}
