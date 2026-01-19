<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLhpDocumentRequest;
use App\Models\LhpDocument;
use App\Models\Offer;
use App\Models\OfferSample;
use App\Queries\LhpVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LhpDocumentController extends Controller
{
    public function summary(Request $request)
    {
        $role = auth()->user()
        ->roles()
        ->whereIn('name', [
            'admin_penawaran',
            'analis',
            'penyelia',
            'admin_input_lhp',
            'manager_teknis',
            'admin_premlim',
        ])
        ->value('name');

        $base = LhpVisibility::forRole($role)->with('currentReview');

        if (!$request->filled('start_date') && !$request->filled('end_date')) {
            $base->whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]);
        }

        if ($request->filled('start_date')) {
            $base->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $base->whereDate('created_at', '<=', $request->end_date);
        }

        $summary = [];
        $summary['all'] = (clone $base)->count();

        switch ($role) {
            /*
            |--------------------------------------------------------------------------
            | ADMIN PENAWARAN
            |--------------------------------------------------------------------------
            */
            case 'admin_penawaran':
                $summary['menunggu_verif_lhp'] = (clone $base)
                    ->where('status', 'draft')
                    ->count();

                $summary['cek_analisis_hasil'] = (clone $base)
                    ->whereIn('status', ['in_review', 'in_analysis'])
                    ->count();

                // ✅ Cek Pembayaran Pelanggan (pending ONLY)
                $summary['lhp_disetujui'] = (clone $base)
                 ->where('status', 'validated')
                    ->count();
                break;

            case 'analis':
                $summary['analisa_data'] = (clone $base)->where('status', 'draft')->count();
                $summary['lhp_telah_diisi'] = (clone $base)->where('status', 'in_analysis')->count();
                $summary['revisi_lhp'] = (clone $base)->where('status', 'revised')->count();
                break;

            case 'penyelia':
                $summary['verifikasi_lhp'] = (clone $base)->where('status', 'in_analysis')->count();
                $summary['lhp_terverifikasi'] = (clone $base)->where('status', 'in_review')
                ->whereHas('reviews', fn ($q) => $q->where('status', 'approved')->where('role', 'analis')
                )
                ->count();
                $summary['review_revisi'] = (clone $base)->where('status', 'revised')
                ->whereHas('currentReview', fn ($q) => $q->where('status', 'pending')->where('role', 'analis')
                )->count();

                break;
            case 'admin_input_lhp':
                $summary['cek_lhp'] = (clone $base)->where('status', 'in_review')
                ->whereHas('currentReview', fn ($q) => $q->where('role', 'admin_input_lhp')
                )
                ->count();

                $summary['hasil_selesai_dicek'] = (clone $base)->where('status', 'in_review')
                ->whereHas('reviews', fn ($q) => $q->where('status', 'approved')->where('role', 'analis')
                )
                ->count();
                $summary['lhp_direvisi'] = (clone $base)->where('status', 'revised')
                ->whereHas('currentReview', fn ($q) => $q->where('status', 'revised')->where('role', 'admin_input_lhp')
                )->count();
                break;
                /*
                |--------------------------------------------------------------------------
                | ADMIN KUPTDK
                |--------------------------------------------------------------------------
                */
        }

        return response()->json($summary);
    }

    public function store(StoreLhpDocumentRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $lhp = LhpDocument::create([
                'offer_id' => $request->offer_id,
                'job_number' => $request->job_number,
                'tanggal_dilaporkan' => $request->tanggal_dilaporkan,
                'status' => 'draft',
            ]);

            $lhp->details()->createMany($request['details']);

            return response()->json([
                'message' => 'LHP berhasil dibuat',
                'data' => $lhp->load('details'),
            ], 201);
        });
    }

    public function show($id)
    {
        $lhpDocument = LhpDocument::query()
            ->with('details')
            ->findOrFail($id);

        $offer = Offer::query()
            ->select([
                'offers.id as offer_id',
                'offers.offer_number',
                'offers.title',
                'offers.location',
                'customers.name as customer_name',
                'cc.name as pic_name',
                'cc.phone as pic_phone',
                'cc.position as pic_position',
            ])
            ->join('customers', 'customers.id', '=', 'offers.customer_id')
            ->leftJoinSub(
                DB::table('customer_contacts')
                    ->select('customer_id', 'name', 'position', 'phone'),
                'cc',
                'cc.customer_id',
                '=',
                'customers.id'
            )
            ->withCount('samples')
            ->where('offers.id', $lhpDocument->offer_id)
            ->firstOrFail();

        $samples = OfferSample::query()
            ->where('offer_id', $lhpDocument->offer_id)
            ->with(['parameters.testParameter.testMethod'])
            ->get();

        return response()->json([
            'lhp_document' => $lhpDocument,
            'offer' => [
                'offer_id' => $offer->offer_id,
                'offer_number' => $offer->offer_number,
                'title' => $offer->title,
                'location' => $offer->location,
                'customer_name' => $offer->customer_name,
                'pic_name' => $offer->pic_name,
                'pic_phone' => $offer->pic_phone,
                'pic_position' => $offer->pic_position,
                'total_samples' => $offer->samples_count,
            ],
            'samples' => $samples,
        ]);
    }
}
