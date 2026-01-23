<?php

namespace App\Queries;

use App\Models\LhpDocument;

class LhpVisibility
{
    public static function forRole(string $role)
    {
        return match ($role) {
            'admin_login' => self::admin_login(),
            'analis' => self::analis(),
            'penyelia_lab' => self::penyelia_lab(),
            'admin_input_lhp' => self::admin_input_lhp(),
            'manager_teknis' => self::manager_teknis(),
            'admin_premlim' => self::admin_premlim(),
            default => LhpDocument::query()->whereRaw('1=0'),
        };
    }

    private static function admin_login()
    {
        return LhpDocument::query();
    }

    private static function analis()
    {
        return LhpDocument::query()->whereIn('status', ['draft', 'in_analysis', 'revised']);
    }

    private static function penyelia_lab()
    {
        return LhpDocument::query()->whereIn('status', ['in_review', 'in_analysis', 'revised'])
                 ->whereHas('reviews.reviewStep', function ($q) {
                     $q->whereIn('code', [
                         'admin_input_lhp',
                     ]);
                 });
    }

    private static function admin_input_lhp()
    {
        return LhpDocument::query()->whereIn('status', ['in_review', 'revised'])
                ->whereHas('reviews.reviewStep', function ($q) {
                    $q->whereIn('code', [
                        'admin_input_lhp',
                    ]);
                });
    }

    private static function manager_teknis()
    {
        return LhpDocument::query()->whereIn('status', ['in_review', 'revised'])
        ->whereHas('reviews.reviewStep', function ($q) {
            $q->whereIn('code', [
                'manager_teknis',
            ]);
        });
    }

    private static function admin_premlim()
    {
        return LhpDocument::query()->whereIn('status', ['in_review', 'revised'])
        ->whereHas('reviews.reviewStep', function ($q) {
            $q->whereIn('code', [
                'admin_premlim',
            ]);
        });
    }
}
