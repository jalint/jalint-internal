<?php

namespace App\Queries;

use App\Models\Offer;
use App\Models\TaskLetter;

class TaskLetterVisibility
{
    public static function forRole(string $role)
    {
        return match ($role) {
            'penyelia' => self::penyeliaLapangan(),
            'ppcu' => self::ppcu(),
            'manager_teknis' => self::managerTeknis(),
            default => TaskLetter::query()->whereRaw('1=0'),
        };
    }

    /**
     * ADMIN PENAWARAN
     * Semua penawaran adalah domain dia.
     */
    private static function penyeliaLapangan()
    {
        return Offer::query()
            ->whereHas('invoice')
            ->with([
                'customer:id,name',
                'invoice:id,offer_id,invoice_number',
                'taskLetter',
            ]);
    }

    /**
     * MANAGER TEKNIS
     * Fokus teknis & hasil akhir.
     */
    private static function managerTeknis()
    {
        return Offer::query()
            ->whereHas('taskLetter', function ($query) {
                $query->whereIn('status', ['approved', 'confirmed', 'pending', 'revised']);
            })
            ->with([
                'customer:id,name',
                'invoice:id,offer_id,invoice_number',
                'taskLetter',
            ]);
    }

    /**
     * ADMIN KUPTDK
     * Mulai dari kaji ulang teknis ke atas.
     */
    private static function ppcu()
    {
        return Offer::query()
          ->whereHas('invoice')
          ->whereHas('taskLetter', function ($query) {
              $query->whereIn('status', ['approved', 'confirmed']);
          })
          ->with([
              'customer:id,name',
              'invoice:id,offer_id,invoice_number',
              'taskLetter',
          ]);
    }
}
