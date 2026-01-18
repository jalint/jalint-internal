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
        return Offer::query()->whereIn('status', ['in_review', 'approved', 'completed', 'rejected'])
                  ->whereHas('reviews.reviewStep', function ($q) {
                      $q->whereIn('code', [
                          'customer', 'manager_admin', 'manage_teknis', 'admin_kuptdk',
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

            // Offer sudah pernah masuk alur admin / teknis
            ->whereHas('reviews.reviewStep', function ($q) {
                $q->whereIn('code', [
                    'manager_admin',
                    'manager_teknis',
                ]);
            })

            // ❌ BLOKIR jika customer masih pending
            ->whereDoesntHave('reviews', function ($q) {
                $q->where('decision', 'pending')
                  ->whereHas(
                      'reviewStep',
                      fn ($qs) => $qs->where('code', 'customer')
                  );
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
            ->whereHas('reviews.reviewStep', function ($q) {
                $q->whereIn('code', [
                    'manager_teknis',
                    'customer',
                ]);
            });
    }
}
