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

    private static function adminPenawaran()
    {
        return Offer::query()
            ->where(function ($q) {
                $q->whereIn('status', [
                    'draft',
                    'in_review',
                    'approved',
                    'completed',
                    'rejected',
                ]);
            });
    }

    private static function adminKuptdk()
    {
        return Offer::query()
            ->where(function ($q) {
                $q->whereIn('status', [
                    'in_review',
                    'approved',
                    'completed',
                    'rejected',
                ]);
            })
            ->whereHas('currentReview.reviewStep', function ($q) {
                $q->whereIn('code', [
                    'admin_kuptdk',
                    'manager_admin',
                    'manager_teknis',
                    'customer',
                ]);
            });
    }

    private static function managerAdmin()
    {
        return Offer::query()
            ->where(function ($q) {
                $q->whereIn('status', [
                    'in_review',
                    'approved',
                    'completed',
                    'rejected',
                ]);
            })
            ->whereHas('currentReview.reviewStep', function ($q) {
                $q->whereIn('code', [
                    'manager_admin',
                    'manager_teknis',
                ]);
            });
    }

    private static function managerTeknis()
    {
        return Offer::query()
            ->where(function ($q) {
                $q->whereIn('status', [
                    'in_review',
                    'approved',
                    'completed',
                    'rejected',
                ]);
            })
            ->whereHas('currentReview.reviewStep', function ($q) {
                $q->whereIn('code', [
                    'manager_teknis',
                    'customer',
                ]);
            });
    }
}
