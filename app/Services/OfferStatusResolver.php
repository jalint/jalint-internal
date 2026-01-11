<?php

namespace App\Services;

use App\Models\Offer;

class OfferStatusResolver
{
    public static function resolve(Offer $offer, string $role): string
    {
        $review = $offer->currentReview;

        // ===== STATE BESAR (FINAL) =====
        if ($offer->status === 'rejected') {
            return 'Direvisi';
        }

        if ($offer->status === 'completed') {
            return 'Completed';
        }

        if ($offer->status === 'approved') {
            return 'Disetujui';
        }

        if (!$review) {
            return 'Draft';
        }

        // ===== WORKFLOW STATE =====
        return match ($role) {
            /*
             * =========================
             * ADMIN PENAWARAN
             * =========================
             */
            'admin_penawaran' => match (true) {
                $offer->created_by_type === 'customer'
                && $review->decision === 'pending'
                && $review->reviewStep->code === 'admin_penawaran' => 'Verif Penawaran Pelanggan',

                $offer->status === 'in_review'
                && $review->reviewStep->code !== 'admin_penawaran' => 'Proses Kaji Ulang',

                default => 'Proses Kaji Ulang',
            },

            /*
             * =========================
             * ADMIN KUPTDK
             * =========================
             */
            'admin_kuptdk' => match (true) {
                $review->reviewStep->code === 'admin_kuptdk' => 'Kaji Ulang',

                $review->reviewStep->code === 'manager_admin'
                && $review->decision === 'pending' => 'Menunggu Persetujuan MA',

                $offer->hasApprovedBy('manager_admin')
                && $review->reviewStep->code === 'manager_teknis' => 'Disetujui MA',

                $offer->hasApprovedBy('manager_teknis')
                && $review->reviewStep->code === 'customer' => 'Disetujui MT',

                default => 'Kaji Ulang',
            },

            /*
             * =========================
             * MANAGER ADMIN
             * =========================
             */
            'manager_admin' => match (true) {
                $review->reviewStep->code === 'manager_admin' => 'Verifikasi Kaji Ulang',

                $review->reviewStep->code === 'manager_teknis'
                && $review->decision === 'pending' => 'Menunggu Persetujuan MT',

                default => 'Verifikasi Kaji Ulang',
            },

            /*
             * =========================
             * MANAGER TEKNIS
             * =========================
             */
            'manager_teknis' => match (true) {
                $review->reviewStep->code === 'manager_teknis' => 'Verifikasi Kaji Ulang',

                $offer->hasApprovedBy('manager_teknis') => 'Disetujui MT',

                default => 'Verifikasi Kaji Ulang',
            },

            default => 'Unknown',
        };
    }
}
