<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\TaskLetter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TaskLetterController extends Controller
{
    public function summary()
    {
        // $role = auth()->user()->roles->first()->name;

        $base = Offer::query()
            ->whereHas('invoice')
            ->whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]);

        $summary['all'] = (clone $base)->count();

        if (auth()->user()->hasRole('penyelia')) {
            $summary['menunggu_surat_tugas'] = (clone $base)
                ->whereDoesntHave('taskLetter')
                ->count();

            $summary['surgas_dikirim'] = (clone $base)
                ->whereHas('taskLetter')
                ->count();
        }

        if (auth()->user()->hasRole('ppcu')) {
            $summary['konfirmasi_surat_tugas'] = (clone $base)
                ->whereHas('taskLetter', fn ($q) => $q->where('status', 'pending')
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
        $role = auth()->user()->roles->first()->name;

        $filter = $request->query('filter', 'all');
        $search = $request->query('search');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = Offer::query()
            ->whereHas('invoice')
            ->with([
                'customer:id,name',
                'invoice:id,offer_id,invoice_number',
                'taskLetter',
            ]);

        /*
         * =========================
         * DEFAULT: BULAN BERJALAN
         * =========================
         */
        if (!$startDate && !$endDate) {
            $query->whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]);
        }

        /*
         * =========================
         * FILTER TANGGAL
         * =========================
         */
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        /*
         * =========================
         * FILTER ROLE + STATUS
         * =========================
         */
        switch ($role) {
            case 'penyelia':
                match ($filter) {
                    'waiting_task_letter' => $query->whereDoesntHave('taskLetter'),
                    'task_letter_sent' => $query->whereHas('taskLetter'),
                    default => null,
                };
                break;

            case 'ppcu':
                match ($filter) {
                    'confirm_task_letter' => $query->whereHas(
                        'taskLetter',
                        fn ($q) => $q->where('status', 'pending')
                    ),
                    'task_executed' => $query->whereHas(
                        'taskLetter',
                        fn ($q) => $q->where('status', 'confirmed')
                    ),
                    default => null,
                };
                break;
        }

        /*
         * =========================
         * SEARCH
         * =========================
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
         * =========================
         * DISPLAY STATUS
         * =========================
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
            'task_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'officers' => ['required', 'array', 'min:1'],
            'officers.*.id' => ['required', 'exists:employees,id'],
            'officers.*.position' => ['nullable', 'string'],
            'officers.*.description' => ['nullable', 'string'],
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

            $taskLetter = TaskLetter::create([
                'offer_id' => $offer->id,
                'task_letter_number' => $this->generateTaskLetterNumber(),
                'task_date' => $request->task_date,
                'note' => $request->note,
                'status' => 'pending',
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
                'taskLetter.officers',
            ])
            ->firstOrFail();

        return response()->json($offer);
    }

    public function update(Request $request, $offerId)
    {
        $request->validate([
            'task_date' => ['required', 'date'],
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
                'task_date' => $request->task_date,
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

    public function review($id)
    {
        $task = TaskLetter::findOrFail($id);

        if ($task->status !== 'pending') {
            abort(400, 'Surat tugas tidak dalam status pending atau sudah dikonfirmasi');
        }

        $task->status = 'confirmed';
        $task->save();

        return response()->json(['message' => 'Surat Tugas Dikofirmasi']);
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
        if (!$offer->invoice) {
            return '-';
        }

        if (!$offer->taskLetter) {
            return 'Menunggu Surat Tugas';
        }

        if ($role === 'ppcu') {
            return match ($offer->taskLetter->status) {
                'pending' => 'Konfirmasi Surat Tugas',
                'confirmed' => 'Tugas Dilaksanakan',
                default => 'Surat Tugas',
            };
        }

        return 'Surgas Dikirim';
    }
}
