<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOfferRequest;
use App\Http\Requests\UpdateOfferRequest;
use App\Models\Offer;
use App\Models\OfferReview;
use App\Models\ReviewStep;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $offers = Offer::with([
            'reviews' => function ($q) {
                $q->where('decision', 'pending')
                  ->with('reviewStep');
            }, 'createdBy:id,name',
        ])
         ->orderByDesc('created_at')
         ->paginate();

        return response()->json($offers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOfferRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $status = $request->boolean('is_draft')
                    ? 'draft'
                    : 'in_review';

                $offer = Offer::create([
                    'offer_number' => $this->generateOfferNumber(),
                    'title' => $request->title,

                    'customer_id' => $request->customer_id,
                    'customer_contact_id' => $request->customer_contact_id,

                    'offer_date' => $request->offer_date,
                    'expired_date' => $request->expired_date,

                    'request_number' => $request->request_number,
                    'template_id' => $request->template_id,

                    'additional_description' => $request->additional_description,

                    'discount_amount' => $request->discount_amount ?? 0,
                    'vat_percent' => $request->vat_percent ?? 0,
                    'withholding_tax_percent' => $request->withholding_tax_percent ?? 0,

                    'status' => $status,
                    'created_by' => auth()->id(),
                ]);

                foreach ($request->details as $detail) {
                    $offer->details()->create([
                        'test_parameter_id' => $detail['test_parameter_id'],
                        'price' => $detail['price'],
                        'qty' => $detail['qty'],
                        'test_package_id' => $detail['test_package_id'] ?? null,
                        'subkon_id' => 1, // Default JLI
                    ]);
                }

                /*
                 * Jika BUKAN draft → langsung masuk workflow review
                 */
                if ($status === 'in_review') {
                    $firstStep = ReviewStep::where('code', 'admin_kuptdk')->firstOrFail();

                    OfferReview::create([
                        'offer_id' => $offer->id,
                        'review_step_id' => $firstStep->id,
                        'reviewer_id' => null,
                        'decision' => 'pending',
                    ]);
                }

                return response()->json($offer);
            });
        } catch (\Throwable $th) {
            Log::error('Failed to create offer', ['error' => $th->getMessage(), 'line' => $th->getLine(), 'code' => $th->getCode()]);

            return response()->json(['message' => 'Terjadi Kesalahan'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $offer = Offer::with([
            'template',
            // review aktif
            'currentReview.reviewStep',

            // history review
            'reviews' => function ($q) {
                $q->where('decision', '!=', 'pending')
                  ->orderBy('created_at');
            },
            'reviews.reviewStep',

            // detail penawaran (WAJIB eager load)
            'details.subkon',
            'details.testParameter.testPackages',
        ])->findOrFail($id);

        $currentReview = $offer->currentReview;

        $canReview =
            $offer->status === 'in_review'
            && $currentReview
            && auth()->user()->hasRole($currentReview->reviewStep->code);

        /**
         * GROUPING PARAMETER BERDASARKAN TEST PACKAGE.
         */
        $groupedParameters = $offer->details
            ->groupBy(function ($detail) {
                return optional(
                    $detail->testParameter
                        ->testPackages
                        ->first()
                )->id ?? 'no_package';
            })
            ->map(function ($items) {
                $package = optional(
                    $items->first()
                        ->testParameter
                        ->testPackages
                        ->first()
                );

                return [
                    'test_package' => $package ? [
                        'id' => $package->id,
                        'name' => $package->name,
                        'regulation' => $package->regulation ?? null,
                    ] : null,

                    'parameters' => $items->map(function ($detail) {
                        return [
                            'id' => $detail->testParameter->id,
                            'name' => $detail->testParameter->name,
                            'method' => $detail->testParameter->method ?? null,
                            'price' => $detail->price,
                            'qty' => $detail->qty,

                            // RELASI BELONGS TO → AKSES LANGSUNG
                            'subkon' => $detail->subkon ? [
                                'id' => $detail->subkon->id,
                                'name' => $detail->subkon->name,
                            ] : null,
                        ];
                    })->values(),
                ];
            })
            ->values();

        // sembunyikan details mentah
        $offer->makeHidden(['details']);

        return response()->json([
            'can_review' => $canReview,
            'offer' => $offer,
            'current_step' => $currentReview ? [
                'code' => $currentReview->reviewStep->code,
                'name' => $currentReview->reviewStep->name,
            ] : null,
            'details' => $groupedParameters,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOfferRequest $request, $id)
    {
        $user = auth()->user();

        return DB::transaction(function () use ($request, $id, $user) {
            /**
             * 1️⃣ Ambil offer + lock.
             */
            $offer = Offer::with([
                'reviews.reviewStep',
                'details',
            ])
                ->lockForUpdate()
                ->findOrFail($id);

            /*
             * 2️⃣ Validasi STATE & ROLE
             */
            if ($offer->status !== 'rejected') {
                abort(400, 'Penawaran hanya dapat direvisi jika status rejected');
            }

            if (!$user->hasRole('admin_kuptdk')) {
                abort(403, 'Hanya Admin KUPTDK yang dapat merevisi penawaran');
            }

            /**
             * 3️⃣ Pastikan review TERAKHIR adalah REJECT.
             */
            $lastReview = $offer->reviews()
                ->latest('created_at')
                ->first();

            if (!$lastReview || $lastReview->decision !== 'rejected') {
                abort(400, 'Penawaran tidak berada pada kondisi revisi');
            }

            /*
             * 4️⃣ Update OFFER HEADER
             */
            $offer->update([
                'title' => $request->title,
                'customer_id' => $request->customer_id,
                'customer_contact_id' => $request->customer_contact_id,
                'offer_date' => $request->offer_date,
                'expired_date' => $request->expired_date,
                'request_number' => $request->request_number,
                'template_id' => $request->template_id,
                'additional_description' => $request->additional_description,
                'discount_amount' => $request->discount_amount ?? 0,
                'vat_percent' => $request->vat_percent ?? 0,
                'withholding_tax_percent' => $request->withholding_tax_percent ?? 0,
                'status' => 'in_review',
                'testing_activities' => $request->testing_activities,
            ]);

            /*
             * 5️⃣ REFRESH DETAIL PENAWARAN
             * (hapus & recreate → paling aman untuk revisi)
             */
            $offer->details()->delete();

            foreach ($request->details as $detail) {
                $offer->details()->create([
                    'test_parameter_id' => $detail['test_parameter_id'],
                    'price' => $detail['price'],
                    'qty' => $detail['qty'],
                    'subkon_id' => 1, // default internal
                ]);
            }

            /**
             * 6️⃣ SNAPSHOT: Admin KUPTDK AUTO-APPROVE.
             */
            $adminKuptdkStep = ReviewStep::where('code', 'admin_kuptdk')->firstOrFail();

            OfferReview::create([
                'offer_id' => $offer->id,
                'review_step_id' => $adminKuptdkStep->id,
                'decision' => 'approved',
                'reviewer_id' => $user->id,
                'reviewed_at' => now(),
                'note' => 'Revisi penawaran setelah penolakan',
            ]);

            /**
             * 7️⃣ LANJUT KE MANAGER ADMIN.
             */
            $managerAdminStep = ReviewStep::where(
                'sequence_order',
                '>',
                $adminKuptdkStep->sequence_order
            )
                ->orderBy('sequence_order')
                ->first();

            if (!$managerAdminStep) {
                abort(500, 'Step review selanjutnya tidak ditemukan');
            }

            OfferReview::create([
                'offer_id' => $offer->id,
                'review_step_id' => $managerAdminStep->id,
                'decision' => 'pending',
            ]);

            /*
             * 8️⃣ DONE
             */
            return response()->json([
                'message' => 'Penawaran berhasil direvisi dan dikirim ulang untuk review',
            ]);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Offer $offer)
    {
    }

    public function review(Request $request, $id)
    {
        $rules = [
            'decision' => 'required|in:approved,rejected',
            'note' => 'nullable|string',
        ];

        // HANYA admin KUPTDK yang wajib kirim details
        if (auth()->user()->hasRole('admin_kuptdk')) {
            $rules['details'] = 'required|array';
            $rules['details.*.id'] = 'required|integer|exists:offer_details,id';
            $rules['details.*.test_parameter_id'] = 'required|exists:test_parameters,id';
            $rules['details.*.subkon_id'] = 'required|exists:subkons,id';
            $rules['details.*.price'] = 'required|min:0';
            $rules['details.*.is_delete'] = 'sometimes|boolean';
            $rules['testing_activities'] = 'required';
        } else {
            // role lain: optional
            $rules['details'] = 'nullable|array';
        }

        $validated = $request->validate($rules);

        if (isset($validated['details'])) {
            $validated['details'] = collect($validated['details'])
                ->map(function ($detail) {
                    $detail['is_delete'] = $detail['is_delete'] ?? false;

                    return $detail;
                })
                ->toArray();
        }

        $user = auth()->user();

        return DB::transaction(function () use ($validated, $id, $user) {
            $offer = Offer::with('currentReview.reviewStep')
                ->lockForUpdate()
                ->findOrFail($id);

            // 1. Offer harus dalam review
            if ($offer->status !== 'in_review') {
                abort(400, 'Offer tidak dalam proses review');
            }

            $currentReview = $offer->currentReview;
            // 2. Harus ada review aktif
            if (!$currentReview) {
                abort(400, 'Tidak ada review aktif');
            }

            // 3. Role harus sesuai step
            if (!$user->hasRole($currentReview->reviewStep->code)) {
                abort(403, 'Anda tidak berhak mereview penawaran ini');
            }

            // 4. Update review saat ini
            $currentReview->update([
                'decision' => $validated['decision'],
                'note' => $validated['note'],
                'reviewer_id' => $user->id,
                'reviewed_at' => now(),
            ]);

            // 5. Jika REJECT → stop workflow
            if ($validated['decision'] === 'rejected') {
                $offer->update([
                    'status' => 'rejected',
                ]);

                return response()->json([
                    'message' => 'Penawaran ditolak',
                ]);
            }

            if ($currentReview->reviewStep->code == 'admin_kuptdk') {
                foreach ($validated['details'] as $detail) {
                    if ($detail['is_delete']) {
                        $offer->details()
                            ->where('id', $detail['id'])
                            ->delete();
                    } else {
                        $offer->details()
                            ->where('id', $detail['id'])
                            ->update([
                                'test_parameter_id' => $detail['test_parameter_id'],
                                'subkon_id' => $detail['subkon_id'],
                                'price' => $detail['price'],
                            ]);
                    }
                }

                $offer->update([
                    'testing_activities' => $validated['testing_activities'],
                ]);
            }

            // 6. Cari step berikutnya
            $nextStep = ReviewStep::where('sequence_order', '>', $currentReview->reviewStep->sequence_order)
                ->orderBy('sequence_order')
                ->first();

            // 7. Kalau masih ada step → lanjut review
            if ($nextStep) {
                OfferReview::create([
                    'offer_id' => $offer->id,
                    'review_step_id' => $nextStep->id,
                    'decision' => 'pending',
                ]);

                return response()->json([
                    'message' => 'Review disetujui, lanjut ke tahap berikutnya',
                ]);
            }

            // 8. Kalau tidak ada step → FINAL APPROVE
            $offer->update([
                'status' => 'approved',
            ]);

            return response()->json([
                'message' => 'Penawaran disetujui sepenuhnya',
            ]);
        });
    }

    public function cancel(Request $request, $id)
    {
        $user = auth()->user();

        return DB::transaction(function () use ($id, $user, $request) {
            $offer = Offer::lockForUpdate()->findOrFail($id);

            // 1. Tidak boleh cancel kalau sudah approved
            if ($offer->status === 'approved') {
                abort(400, 'Penawaran yang sudah disetujui tidak dapat dibatalkan');
            }

            // 2. Tidak boleh cancel dua kali
            if ($offer->status === 'cancelled') {
                abort(400, 'Penawaran sudah dibatalkan');
            }

            // 3. (Opsional) Validasi hak akses
            // contoh: hanya admin_penawaran / admin_kuptdk
            if (!$user->hasAnyRole(['admin_penawaran', 'admin_kuptdk'])) {
                abort(403, 'Anda tidak berhak membatalkan penawaran');
            }

            // 4. Update status offer
            $offer->update([
                'status' => 'cancelled',
            ]);

            // 5. Tutup review aktif (jika ada)
            OfferReview::where('offer_id', $offer->id)
                ->where('decision', 'pending')
                ->update([
                    'decision' => 'cancelled',
                    'reviewer_id' => $user->id,
                    'reviewed_at' => now(),
                    'note' => $request->note ?? 'Penawaran dibatalkan',
                ]);

            return response()->json([
                'message' => 'Penawaran berhasil dibatalkan',
            ]);
        });
    }

    protected function generateOfferNumber(): string
    {
        $now = Carbon::now();

        $year = $now->year;
        $monthRoman = $this->toRoman($now->month);

        $lastOffer = Offer::whereYear('created_at', $year)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $lastSequence = 0;

        if ($lastOffer) {
            $parts = explode('/', $lastOffer->offer_number);
            $lastSequence = (int) ($parts[0] ?? 0);
        }

        $sequence = $lastSequence + 1;

        $sequenceFormatted = str_pad($sequence, 4, '0', STR_PAD_LEFT);

        $unitCode = config('offer.unit_code', 'Jalint-Lab');

        return "{$sequenceFormatted}/{$unitCode}/{$monthRoman}/{$year}";
    }

    protected function toRoman(int $month): string
    {
        $map = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        return $map[$month];
    }

    public function canReview(Offer $offer, User $user): bool
    {
        if ($offer->status !== 'in_review') {
            return false;
        }

        $currentReview = $offer->reviews
            ->where('decision', 'pending')
            ->first();

        if (!$currentReview) {
            return false;
        }

        return $currentReview->reviewStep->code === $user->role;
    }
}
