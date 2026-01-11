<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\TaskLetter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TaskLetterController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter');
        // all | no_task_letter | has_task_letter

        $query = Offer::query()
            ->where('status', 'approved')
            ->whereHas('invoice')
            ->with([
                'customer:id,name',
                'taskLetter:id,offer_id,task_letter_number',
            ])
            ->latest();

        switch ($filter) {
            case 'no_task_letter':
                $query->whereDoesntHave('taskLetter');
                break;

            case 'has_task_letter':
                $query->whereHas('taskLetter');
                break;

            case 'all':
            default:
                // tidak perlu apa-apa
                break;
        }

        $offers = $query->paginate(15);

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
}
