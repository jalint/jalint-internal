<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Requests\UploadEmployeePhotoRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $perPage = (int) request()->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // guardrail: 1–100

        $search = request()->input('search');

        $query = Employee::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                // tambahkan kolom lain bila perlu
                ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $sampleType = $query->with('certifications:id,name')->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($sampleType);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request)
    {
        $result = DB::transaction(function () use ($request) {
            // $user = User::create([
            //     'name' => $request->name,
            //     'email' => $request->email,
            //     'password' => bcrypt('jalint2025')]);

            // 2. Gabungkan user_id ke data employee
            // $employeeData = array_merge(
            //     $request->validated(),
            //     ['user_id' => $user->id]
            // );
            $request->merge(['created_by' => auth()->user()->name]);
            // 3. Buat employee
            $employee = Employee::create($request->all());

            // 4. Attach certifications
            if ($request->filled('certifications')) {
                $employee->certifications()->attach($request->certifications);
            }

            return $employee;
        });

        return response()->json($result, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        return response()->json($employee->load('certifications:id,name'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $request->merge(['updated_by' => auth()->user()->name]);

        DB::transaction(function () use ($request, $employee) {
            // 1. Update employee
            $employee->update($request->all());

            // 2. Update user (jika ada)
            // if ($employee->user) {
            //     $employee->user->update([
            //         'name' => $employee->name,
            //         'email' => $employee->email,
            //     ]);
            // }

            // 3. Sync certifications
            if ($request->has('certifications')) {
                $employee->certifications()->sync($request->certifications);
            }
        });

        return response()->json($employee->fresh(['user', 'certifications']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        $employee->delete();

        return response()->noContent();
    }

    public function uploadPhoto(UploadEmployeePhotoRequest $request, Employee $employee)
    {
        DB::transaction(function () use ($request, $employee) {
            // Hapus foto lama jika ada
            if ($employee->photo_path && Storage::disk('public')->exists($employee->photo_path)) {
                Storage::disk('public')->delete($employee->photo_path);
            }

            // Simpan foto baru
            $path = $request->file('photo')->store(
                'employees/photos',
                'public'
            );

            // Update path ke employee
            $employee->update([
                'photo_path' => $path,
            ]);
        });

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'photo_url' => asset('storage/'.$employee->photo_path),
        ]);
    }

    public function linkedAccount()
    {
    }
}
