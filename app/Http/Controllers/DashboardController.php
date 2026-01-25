<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SINGLE DASHBOARD ENDPOINT
    |--------------------------------------------------------------------------
    */
    public function dashboardSummary(Request $request)
    {
        $isCustomer = auth('customer')->check();

        $response = [
            'penawaran' => [
                'summary' => $this->metricSummary($request, 'offers'),
                'quarterly' => $this->metricPerQuarter('offers'),
            ],

            'lab_job' => [
                'summary' => $this->metricSummary($request, 'lhp_documents'),
                'quarterly' => $this->metricPerQuarter('lhp_documents'),
            ],

            'hasil_pengujian' => [
                'summary' => $this->metricSummary(
                    $request,
                    'lhp_document_parameters',
                    fn ($q) => $q->whereNotNull('lhp_document_parameters.result')
                ),
                'quarterly' => $this->metricPerQuarter(
                    'lhp_document_parameters',
                    fn ($q) => $q->whereNotNull('lhp_document_parameters.result')
                ),
            ],
        ];

        if (!$isCustomer) {
            $response['transaksi_per_klien'] = $this->transaksiPerKlien($request);
        }

        if ($isCustomer) {
            $response['invvoice_belum_dibayar'] = $this->tagihanBelumDibayarCustomer();
        }

        return response()->json($response);
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSAKSI PER KLIEN (ADMIN ONLY)
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
    | METRIC SUMMARY
    |--------------------------------------------------------------------------
    */
    private function metricSummary(
        Request $request,
        string $table,
        ?callable $extraQuery = null
    ): array {
        [$startDate, $endDate] = $this->resolvePeriod($request);
        $customerId = $this->resolveCustomerId();

        $days = $startDate->diffInDays($endDate) + 1;

        $compareEnd = $startDate->copy()->subDay()->endOfDay();
        $compareStart = $compareEnd->copy()->subDays($days - 1)->startOfDay();

        $currentQuery = DB::table($table)
            ->whereBetween("{$table}.created_at", [$startDate, $endDate]);

        $this->applyCustomerFilter($currentQuery, $customerId, $table);

        if ($extraQuery) {
            $extraQuery($currentQuery);
        }

        $currentTotal = $currentQuery->count();

        $previousQuery = DB::table($table)
            ->whereBetween("{$table}.created_at", [$compareStart, $compareEnd]);

        $this->applyCustomerFilter($previousQuery, $customerId, $table);

        if ($extraQuery) {
            $extraQuery($previousQuery);
        }

        $previousTotal = $previousQuery->count();

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
    | METRIC PER QUARTER
    |--------------------------------------------------------------------------
    */
    private function metricPerQuarter(
        string $table,
        ?callable $extraQuery = null
    ) {
        $year = now()->year;
        $customerId = $this->resolveCustomerId();

        $query = DB::table($table)
            ->selectRaw(
                'QUARTER('.$table.'.created_at) as quarter, COUNT(*) as total'
            )
            ->whereYear($table.'.created_at', $year);

        $this->applyCustomerFilter($query, $customerId, $table);

        if ($extraQuery) {
            $extraQuery($query);
        }

        $raw = $query
            ->groupBy(DB::raw('QUARTER('.$table.'.created_at)'))
            ->orderBy('quarter')
            ->get();

        return collect([1, 2, 3, 4])->map(function ($q) use ($raw) {
            return [
                'quarter' => 'Q'.$q,
                'total' => $raw->firstWhere('quarter', $q)->total ?? 0,
            ];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER CONTEXT
    |--------------------------------------------------------------------------
    */
    private function resolveCustomerId(): ?int
    {
        if (auth('customer')->check()) {
            return auth('customer')->user()->customer_id;
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | APPLY CUSTOMER FILTER (JOIN CHAIN FIXED)
    |--------------------------------------------------------------------------
    */
    private function applyCustomerFilter($query, ?int $customerId, string $table): void
    {
        if (!$customerId) {
            return;
        }

        if ($table === 'offers') {
            $query->where('offers.customer_id', $customerId);

            return;
        }

        if ($table === 'lhp_documents') {
            $query
                ->join('offers', 'lhp_documents.offer_id', '=', 'offers.id')
                ->where('offers.customer_id', $customerId);

            return;
        }

        if ($table === 'lhp_document_parameters') {
            $query
                ->join(
                    'lhp_document_details',
                    'lhp_document_parameters.lhp_document_detail_id',
                    '=',
                    'lhp_document_details.id'
                )
                ->join(
                    'lhp_documents',
                    'lhp_document_details.lhp_document_id',
                    '=',
                    'lhp_documents.id'
                )
                ->join(
                    'offers',
                    'lhp_documents.offer_id',
                    '=',
                    'offers.id'
                )
                ->where('offers.customer_id', $customerId);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PERIOD RESOLVER
    |--------------------------------------------------------------------------
    */
    private function resolvePeriod(Request $request): array
    {
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
        } else {
            $startDate = now()->startOfMonth()->startOfDay();
            $endDate = now()->endOfMonth()->endOfDay();
        }

        return [$startDate, $endDate];
    }

    private function tagihanBelumDibayarCustomer()
    {
        $customerId = auth('customer')->user()->customer_id;

        return DB::table('invoices')
            ->join('offers', 'invoices.offer_id', '=', 'offers.id')
            ->leftJoin('invoice_payments', function ($join) {
                $join->on('invoice_payments.invoice_id', '=', 'invoices.id')
                     ->where('invoice_payments.status', 'approved');
            })
            ->where('invoices.customer_id', $customerId)
            ->whereIn('invoices.status', ['unpaid', 'partial'])
            ->groupBy(
                'invoices.id',
                'offers.offer_number',
                'offers.title',
                'offers.offer_date',
                'invoices.invoice_number',
                'invoices.total_amount'
            )
            ->select([
                'offers.offer_number',
                'offers.offer_date',
                'offers.title',
                'invoices.invoice_number',
                'invoices.total_amount',
                DB::raw('COALESCE(SUM(invoice_payments.amount), 0) as total_dibayarkan'),
                DB::raw('
                invoices.total_amount 
                - COALESCE(SUM(invoice_payments.amount), 0)
                as sisa_tagihan
            '),
            ])
            ->orderBy('invoices.due_date')
            ->get();
    }
}
