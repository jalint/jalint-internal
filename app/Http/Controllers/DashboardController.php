<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SINGLE DASHBOARD ENDPOINT (ONE HIT)
    |--------------------------------------------------------------------------
    */

    public function dashboardSummary(Request $request)
    {
        return response()->json([
            'penawaran' => $this->metricSummary($request, 'offers'),

            'lab_job' => $this->metricSummary($request, 'lhp_documents'),

            'hasil_pengujian' => $this->metricSummary(
                $request,
                'lhp_document_parameters',
                fn ($query) => $query->whereNotNull('result')
            ),

            'transaksi_per_klien' => $this->transaksiPerKlien($request),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSAKSI PER KLIEN (PAKAI DATE RANGE YANG SAMA)
    |--------------------------------------------------------------------------
    */

    private function transaksiPerKlien(Request $request)
    {
        [$startDate, $endDate] = $this->resolvePeriod($request);

        return DB::table('offers')
            ->join('customers', 'offers.customer_id', '=', 'customers.id')
            ->whereIn('offers.status', ['approved', 'completed'])
            ->whereBetween('offers.created_at', [$startDate, $endDate])
            ->select([
                'customers.name as customer_name',
                'offers.created_at as tanggal_transaksi',
                'offers.total_amount',
            ])
            ->orderByDesc('offers.created_at')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | CORE METRIC LOGIC (COUNT + PERCENTAGE)
    |--------------------------------------------------------------------------
    */

    private function metricSummary(
        Request $request,
        string $table,
        ?callable $extraQuery = null
    ): array {
        [$startDate, $endDate] = $this->resolvePeriod($request);

        // durasi hari
        $days = $startDate->diffInDays($endDate) + 1;

        // periode pembanding
        $compareEnd = $startDate->copy()->subDay()->endOfDay();
        $compareStart = $compareEnd->copy()->subDays($days - 1)->startOfDay();

        // current
        $currentQuery = DB::table($table)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($extraQuery) {
            $extraQuery($currentQuery);
        }

        $currentTotal = $currentQuery->count();

        // previous
        $previousQuery = DB::table($table)
            ->whereBetween('created_at', [$compareStart, $compareEnd]);

        if ($extraQuery) {
            $extraQuery($previousQuery);
        }

        $previousTotal = 1;  // $previousQuery->count();

        // percentage (defensive)
        $percentageChange = null;

        if ($previousTotal > 0) {
            $percentageChange = round(
                (($currentTotal - $previousTotal) / $previousTotal) * 100,
                1
            );
        }

        return [
            'current_total' => $currentTotal,
            'previous_total' => $previousTotal,
            'percentage_change' => $percentageChange,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PERIOD RESOLVER (SINGLE SOURCE OF TIME)
    |--------------------------------------------------------------------------
    */

    private function resolvePeriod(Request $request): array
    {
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
        } else {
            // default: bulan berjalan (FULL month)
            $startDate = now()->startOfMonth()->startOfDay();
            $endDate = now()->endOfMonth()->endOfDay();
        }

        return [$startDate, $endDate];
    }
}
