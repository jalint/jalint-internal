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
            ->whereIn('status', ['draft', 'in_analysis', 'revised']);
    }

    private static function penyeliaLab()
    {
        return LhpDocument::query()
            ->where(function ($q) {
                // 1️⃣ Sedang menunggu aksi penyelia
                $q->where('status', 'in_review')
                  ->whereHas('currentReview', fn ($qr) => $qr->where('role', 'penyelia_lab')
                  );

                // 2️⃣ Revisi yang benar-benar dibuat oleh penyelia
                $q->orWhere(function ($qr) {
                    $qr->where('status', 'revised')
                       ->whereHas('latestRevisedReview', fn ($qrr) => $qrr->where('role', 'penyelia_lab')
                       );
                });
            });
    }

    private static function adminInputLHP()
    {
        return LhpDocument::query()
                ->where(function ($q) {
                    $q->where('status', 'in_review')
                      ->whereHas('currentReview', fn ($qr) => $qr->where('role', 'admin_input_lhp')
                      );

                    $q->orWhere(function ($qr) {
                        $qr->where('status', 'revised')
                           ->whereHas('latestRevisedReview', fn ($qrr) => $qrr->where('role', 'admin_input_lhp')
                           );
                    });
                });
    }

    private static function managerTeknis()
    {
        return LhpDocument::query()
           ->where(function ($q) {
               $q->where('status', 'in_review')
                 ->whereHas('currentReview', fn ($qr) => $qr->where('role', 'manager_teknis')
                 );

               $q->orWhere(function ($qr) {
                   $qr->where('status', 'revised')
                      ->whereHas('latestRevisedReview', fn ($qrr) => $qrr->where('role', 'manager_teknis')
                      );
               });
           });
    }

    private static function adminPremlim()
    {
        return LhpDocument::query()
               ->where(function ($q) {
                   $q->where('status', 'in_review')
                     ->whereHas('currentReview', fn ($qr) => $qr->where('role', 'admin_premlim')
                     );

                   $q->orWhere(function ($qr) {
                       $qr->where('status', 'revised')
                          ->whereHas('latestRevisedReview', fn ($qrr) => $qrr->where('role', 'admin_premlim')
                          );
                   });
               });
    }
}
