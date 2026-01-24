<?php

namespace App\Services;

use App\Models\LhpDocument;

class LhpDisplayStatus
{
    public static function resolve(LhpDocument $lhp, string $role): string
    {
        return match ($role) {
            /* =====================================================
             | ADMIN PENAWARAN
             ===================================================== */
            'admin_login' => match (true) {
                $lhp->status === 'draft' => 'Menunggu Verifikasi LHP',

                in_array($lhp->status, ['in_review', 'in_analysis', 'revised']) => 'Cek Hasil Analisis',

                $lhp->status === 'validated' => 'LHP Disetujui',

                default => 'Tidak Diketahui',
            },

            /* =====================================================
             | ANALIS
             ===================================================== */
            'analis' => match (true) {
                $lhp->status === 'draft' => 'Analisa Data',

                $lhp->status === 'in_analysis' => 'LHP Telah Diisi',

                $lhp->status === 'revised' => 'Revisi LHP',

                default => 'Tidak Diketahui',
            },

            /* =====================================================
             | PENYELIA
             ===================================================== */
            'penyelia_lab' => match (true) {
                $lhp->status === 'in_analysis' => 'Verifikasi LHP',

                $lhp->status === 'in_review' || $lhp->status === 'validated'
                && $lhp->reviews()
                    ->where('decision', 'approved')
                    ->where('role', 'analis')
                    ->exists() => 'LHP Terverifikasi',

                $lhp->status === 'revised'
                && $lhp->latestRevisedReview?->role === 'penyelia_lab' => 'Review Revisi',

                default => 'Tidak Diketahui',
            },

            /* =====================================================
             | ADMIN INPUT LHP
             ===================================================== */
            'admin_input_lhp' => match (true) {
                $lhp->status === 'in_review'
                && $lhp->currentReview?->role === 'admin_input_lhp' => 'Cek LHP',

                $lhp->status === 'in_review' || $lhp->status === 'validated'
                && $lhp->reviews()
                    ->where('decision', 'approved')
                    ->where('role', 'analis')
                    ->exists() => 'Hasil Selesai Dicek',

                $lhp->status === 'revised'
                && $lhp->latestRevisedReview?->role === 'admin_input_lhp' => 'LHP Direvisi',

                default => 'Tidak Diketahui',
            },

            /* =====================================================
             | MANAGER TEKNIS
             ===================================================== */
            'manager_teknis' => match (true) {
                $lhp->status === 'in_review'
                && $lhp->currentReview?->role === 'manager_teknis' => 'Validasi LHP',

                $lhp->status === 'in_review' || $lhp->status === 'validated'
                && $lhp->reviews()
                    ->where('decision', 'approved')
                    ->where('role', 'manager_teknis')
                    ->exists() => 'LHP Tervalidasi',

                $lhp->status === 'revised'
                && $lhp->latestRevisedReview?->role === 'manager_teknis' => 'LHP Direvisi',

                default => 'Tidak Diketahui',
            },

            /* =====================================================
             | ADMIN PREMLIM
             ===================================================== */
            'admin_premlim' => match (true) {
                $lhp->status === 'in_review'
                && $lhp->currentReview?->role === 'admin_premlim' => 'Validasi LHP',

                $lhp->status === 'in_review' || $lhp->status === 'validated'
                && $lhp->reviews()
                    ->where('decision', 'approved')
                    ->where('role', 'admin_premlim')
                    ->exists() => 'LHP Tervalidasi',

                $lhp->status === 'revised'
                && $lhp->latestRevisedReview?->role === 'admin_premlim' => 'LHP Direvisi',

                default => 'Tidak Diketahui',
            },

            default => 'Tidak Diketahui',
        };
    }
}
