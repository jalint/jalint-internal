<?php

namespace App\Queries;

use App\Models\LhpDocument;

class LhpVisibility
{
    public static function forRole(string $role)
    {
        return match ($role) {
            'admin_login' => self::adminLogin(),
            'analis' => self::analis(),
            'penyelia_lab' => self::penyelia(),
            'admin_input_lhp' => self::adminInput(),
            'manager_teknis' => self::managerTeknis(),
            'admin_premlim' => self::adminPremlim(),
            default => LhpDocument::query()->whereRaw('1=0'),
        };
    }

    private static function adminLogin()
    {
        return LhpDocument::query();
    }

    private static function analis()
    {
        return LhpDocument::query()
            ->whereIn('status', ['draft', 'in_analysis', 'revised']);
    }

    private static function penyelia()
    {
        return LhpDocument::query()
            ->whereIn('status', ['in_review', 'revised'])
            ->whereHas('reviews.reviewStep', fn ($q) => $q->where('code', 'analis')
            );
    }

    private static function adminInput()
    {
        return LhpDocument::query()
            ->whereIn('status', ['in_review', 'revised'])
            ->whereHas('reviews.reviewStep', fn ($q) => $q->where('code', 'admin_input_lhp')
            );
    }

    private static function managerTeknis()
    {
        return LhpDocument::query()
            ->whereIn('status', ['in_review', 'revised'])
            ->whereHas('reviews.reviewStep', fn ($q) => $q->where('code', 'manager_teknis')
            );
    }

    private static function adminPremlim()
    {
        return LhpDocument::query()
            ->whereIn('status', ['in_review', 'revised'])
            ->whereHas('reviews.reviewStep', fn ($q) => $q->where('code', 'admin_premlim')
            );
    }
}
