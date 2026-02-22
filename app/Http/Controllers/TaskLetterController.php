<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\TaskLetter;
use App\Queries\TaskLetterVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TaskLetterController extends Controller
{
    public function summary(Request $request)
    {
        $role = auth()->user()
            ->roles()
            ->whereIn('name', ['ppcu', 'penyelia', 'manager_teknis'])
            ->value('name');

        $base = TaskLetterVisibility::forRole($role);

        // if (!$request->filled('start_date') && !$request->filled('end_date')) {
        //     $base->whereBetween('created_at', [
        //         now()->startOfMonth(),
        //         now()->endOfMonth(),
        //     ]);
        // }

        // if ($request->filled('start_date')) {
        //     $base->whereDate('created_at', '>=', $request->start_date);
        // }

        // if ($request->filled('end_date')) {
        //     $base->whereDate('created_at', '<=', $request->end_date);
        // }

        $summary = [];
        $summary['all'] = (clone $base)->count();

        if (auth()->user()->hasRole('penyelia')) {
            $summary['menunggu_surat_tugas'] = (clone $base)
                ->whereDoesntHave('taskLetter')
                ->count();

            $summary['surgas_dikirim'] = (clone $base)
                ->whereHas('taskLetter')
                ->count();

            $summary['revised'] = (clone $base)
            ->whereHas('taskLetter', fn ($q) => $q->where('status', 'revised')
            )->count();
        }

        if (auth()->user()->hasRole('manager_teknis')) {
            $summary['konfirmasi_surat_tugas'] = (clone $base)
                ->whereHas('taskLetter', fn ($q) => $q->where('status', 'pending')
                )
                ->count();

            $summary['surat_tugas_dikonfirmasi'] = (clone $base)
                ->whereHas('taskLetter', fn ($q) => $q->whereIn('status', ['confirmed', 'approved'])
                )
                ->count();

            $summary['revised'] = (clone $base)
            ->whereHas('taskLetter', fn ($q) => $q->where('status', 'revised')
            )->count();
        }

        if (auth()->user()->hasRole('ppcu')) {
            $summary['konfirmasi_surat_tugas'] = (clone $base)
                ->whereHas('taskLetter', fn ($q) => $q->where('status', 'approved')
                )
                ->count();

            $summary['surgas_dilaksankan'] = (clone $base)
                ->whereHas('taskLetter', fn ($q) => $q->where('status', 'confirmed')
                )
                ->count();
        }

        return response()->json($summary);
    }

    public function index(Request $request)
    {
        $role = auth()->user()
            ->roles()
            ->whereIn('name', ['ppcu', 'penyelia', 'manager_teknis'])
            ->value('name');

        $filter = $request->query('filter', 'all');
        $search = $request->query('search');
        // $startDate = $request->query('start_date');
        // $endDate = $request->query('end_date');

        $query = TaskLetterVisibility::forRole($role);

        /*
        |--------------------------------------------------------------------------
        | DEFAULT: BULAN BERJALAN
        |--------------------------------------------------------------------------
        */
        // if (!$startDate && !$endDate) {
        //     $query->whereBetween('created_at', [
        //         now()->startOfMonth(),
        //         now()->endOfMonth(),
        //     ]);
        // }

        // if ($startDate) {
        //     $query->whereDate('created_at', '>=', $startDate);
        // }

        // if ($endDate) {
        //     $query->whereDate('created_at', '<=', $endDate);
        // }

        /*
        |--------------------------------------------------------------------------
        | FILTER SESUAI SUMMARY (STRICT 1:1)
        |--------------------------------------------------------------------------
        */
        switch ($role) {
            case 'penyelia':
                match ($filter) {
                    'menunggu_surat_tugas' => $query->whereDoesntHave('taskLetter'),

                    'surgas_dikirim' => $query->whereHas('taskLetter', fn ($q) => $q->where('status', '!=', 'revised')
                    ),

                    'revised' => $query->whereHas('taskLetter', fn ($q) => $q->where('status', 'revised')
                    ),

                    default => null,
                };
                break;

            case 'manager_teknis':
                match ($filter) {
                    'konfirmasi_surat_tugas' => $query->whereHas('taskLetter', fn ($q) => $q->where('status', 'pending')
                    ),

                    'surat_tugas_dikonfirmasi' => $query->whereHas('taskLetter', fn ($q) => $q->whereIn('status', ['confirmed', 'approved'])
                    ),

                    'revised' => $query->whereHas('taskLetter', fn ($q) => $q->where('status', 'revised')
                    ),

                    default => null,
                };
                break;

            case 'ppcu':
                match ($filter) {
                    'konfirmasi_surat_tugas' => $query->whereHas('taskLetter', fn ($q) => $q->where('status', 'approved')
                    ),

                    'surgas_dilaksankan' => $query->whereHas('taskLetter', fn ($q) => $q->where('status', 'confirmed')
                    ),

                    default => null,
                };
                break;
        }

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('offer_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")
                  );
            });
        }

        $offers = $query
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        /*
        |--------------------------------------------------------------------------
        | DISPLAY STATUS (UI FRIENDLY)
        |--------------------------------------------------------------------------
        */
        $offers->getCollection()->transform(function ($offer) use ($role) {
            $offer->display_status = $this->resolveTaskLetterStatus($offer, $role);

            return $offer;
        });

        return response()->json($offers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'offer_id' => ['required', 'exists:offers,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'officers' => ['required', 'array', 'min:1'],
            'officers.*.id' => ['required', 'exists:employees,id'],
            'officers.*.position' => ['nullable', 'string'],
            'officers.*.description' => ['nullable', 'string'],
            'surat_rencana_contoh_uji' => ['nullable', 'file', 'mimes:pdf,jpg,png,jpeg', 'max:5120'],
        ]);

        return DB::transaction(function () use ($request) {
            $offer = Offer::lockForUpdate()->whereIn('status', ['approved'])
            ->whereHas('invoice')->findOrFail($request->offer_id);

            if ($offer->status !== 'approved') {
                abort(400, 'Surat tugas hanya bisa dibuat untuk penawaran yang disetujui');
            }

            if ($offer->taskLetter) {
                abort(400, 'Surat tugas sudah dibuat untuk penawaran ini');
            }

            if ($request->hasFile('surat_rencana_contoh_uji')) {
                $fileRencanaContohUji = $request->file('surat_rencana_contoh_uji')
                    ->store('rencana_contoh_uji', 'public');
            }

            $taskLetter = TaskLetter::create([
                'offer_id' => $offer->id,
                'task_letter_number' => $this->generateTaskLetterNumber(),
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'note' => $request->note,
                'status' => 'pending',
                'surat_rencana_contoh_uji' => $fileRencanaContohUji ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($request->officers as $officer) {
                $taskLetter->officers()->create([
                    'employee_id' => $officer['id'],
                    'position' => $officer['position'],
                    'description' => $officer['description'],
                ]);
            }

            return response()->json([
                'message' => 'Surat tugas berhasil dibuat',
            ]);
        });
    }

    public function show($offerId)
    {
        $offer = Offer::query()
            ->where('id', $offerId)
            ->whereHas('invoice')
            ->with([
                'customer:id,name',
                'samples.parameters.testParameter.sampleType',
                'invoice:id,offer_id,invoice_number',
                'taskLetter.officers.employee',
            ])
            ->firstOrFail();

        return response()->json($offer);
    }

    public function update(Request $request, $offerId)
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'officers' => ['required', 'array', 'min:1'],
            'officers.*.name' => ['required', 'string'],
            'officers.*.position' => ['nullable', 'string'],
            'officers.*.description' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($request, $offerId) {
            $offer = Offer::with('taskLetter.officers')
                ->lockForUpdate()
                ->findOrFail($offerId);

            // 1️⃣ Validasi status offer
            if ($offer->status !== 'approved') {
                abort(400, 'Surat tugas hanya bisa diubah untuk penawaran yang disetujui');
            }

            // 2️⃣ Harus sudah ada surat tugas
            $taskLetter = $offer->taskLetter;
            if (!$taskLetter) {
                abort(404, 'Surat tugas belum dibuat');
            }

            // 3️⃣ Update header surat tugas
            $taskLetter->update([
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'note' => $request->note,
                'updated_by' => auth()->id(),
            ]);

            /*
             * 4️⃣ RESET OFFICERS
             * Aman, simpel, dan cocok untuk UI dinamis
             */
            $taskLetter->officers()->delete();

            foreach ($request->officers as $officer) {
                $taskLetter->officers()->create([
                    'name' => $officer['name'],
                    'position' => $officer['position'] ?? null,
                    'description' => $officer['description'] ?? null,
                ]);
            }

            return response()->json([
                'message' => 'Surat tugas berhasil diperbarui',
            ]);
        });
    }

    public function review(Request $request, $id)
    {
        $task = TaskLetter::findOrFail($id);
        $user = auth()->user();

        /*
        |--------------------------------------------------------------------------
        | MANAGER TEKNIS
        |--------------------------------------------------------------------------
        */
        if ($user->hasRole('manager_teknis')) {
            if ($task->status !== 'pending') {
                abort(400, 'Surat tugas tidak dalam status pending');
            }

            $validated = $request->validate([
                'decision' => ['required', 'in:approved,revised'],
            ]);

            $task->update([
                'status' => $validated['decision'],
            ]);

            return response()->json([
                'message' => $validated['decision'] === 'approved'
                    ? 'Surat tugas disetujui oleh Manager Teknis'
                    : 'Surat tugas direvisi oleh Manager Teknis',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | PPCU
        |--------------------------------------------------------------------------
        */
        if ($user->hasRole('ppcu')) {
            if ($task->status !== 'approved') {
                abort(400, 'Surat tugas belum disetujui Manager Teknis');
            }

            $task->update([
                'status' => 'confirmed',
            ]);

            return response()->json([
                'message' => 'Surat tugas dikonfirmasi dan siap dilaksanakan',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | ROLE TIDAK DIIZINKAN
        |--------------------------------------------------------------------------
        */
        abort(403, 'Anda tidak memiliki akses untuk mereview surat tugas');
    }

    protected function generateTaskLetterNumber(): string
    {
        $count = TaskLetter::whereYear('created_at', now()->year)->count() + 1;

        $now = Carbon::now();
        $monthRoman = $this->toRoman($now->month);

        return sprintf(
            '%03d/ST/Jalint-Lab/%s/%s',
            $count,
            $monthRoman,
            now()->year
        );
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

    private function resolveTaskLetterStatus(Offer $offer, string $role): string
    {
        $taskLetter = $offer->taskLetter;

        /*
        |--------------------------------------------------------------------------
        | PENYELIA
        |--------------------------------------------------------------------------
        */
        if ($role === 'penyelia') {
            if (!$taskLetter) {
                return 'Menunggu Surat Tugas';
            }

            if ($taskLetter->status === 'revised') {
                return 'Direvisi';
            }

            return 'Surat Tugas Dikirim';
        }

        /*
        |--------------------------------------------------------------------------
        | MANAGER TEKNIS
        |--------------------------------------------------------------------------
        */
        if ($role === 'manager_teknis') {
            return match ($taskLetter->status) {
                'pending' => 'Konfirmasi Surat Tugas',
                'approved',
                'confirmed' => 'Surat Tugas Dikonfirmasi',
                'revised' => 'Direvisi',
                default => 'Status Tidak Dikenal',
            };
        }

        /*
        |--------------------------------------------------------------------------
        | PPCU
        |--------------------------------------------------------------------------
        */
        if ($role === 'ppcu') {
            return match ($taskLetter->status) {
                'approved' => 'Konfirmasi Surat Tugas',
                'confirmed' => 'Surat Tugas Dilaksanakan',
                default => 'Menunggu Proses',
            };
        }

        /*
        |--------------------------------------------------------------------------
        | FALLBACK
        |--------------------------------------------------------------------------
        */
        return 'Tidak Tersedia';
    }
}
