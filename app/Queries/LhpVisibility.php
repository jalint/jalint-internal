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
            'penyelia_lab' => self::penyeliaLab(),
            'admin_input_lhp' => self::adminInputLHP(),
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
            ->whereIn('status', ['draft', 'in_analysis', 'revised', 'validated']);
    }

    private static function penyeliaLab()
    {
        return LhpDocument::query()->whereIn('status', ['in_analysis', 'in_review', 'validated', 'revised']);
    }

    private static function adminInputLHP()
    {
        return LhpDocument::query()
         ->whereIn('status', ['in_review', 'validated', 'revised']);
    }

    private static function managerTeknis()
    {
        return LhpDocument::query()
            ->whereIn('status', ['in_review', 'validated', 'revised']);
    }

    private static function adminPremlim()
    {
        return LhpDocument::query()
        ->whereIn('status', ['in_review', 'validated', 'revised']);
    }
}
