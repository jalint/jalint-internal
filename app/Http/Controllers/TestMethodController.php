<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTestMethodRequest;
use App\Http\Requests\UpdateTestMethodRequest;
use App\Models\TestMethod;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\JsonResponse;

class TestMethodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $perPage = (int) request()->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $search = request()->input('search');

        $query = TestMethod::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $testMethods = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($testMethods);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTestMethodRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('file')) {
            $data['file'] = $request->file('file')
                ->store('test_methods', 'public');
        }

        $data['created_by'] = auth()->user()->name;

        $testMethod = TestMethod::create($data);

        return response()->json($testMethod, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TestMethod $testMethod)
    {
        return response()->json($testMethod);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTestMethodRequest $request, TestMethod $testMethod): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('file')) {
            // hapus file lama jika ada
            if ($testMethod->file) {
                Storage::disk('public')->delete($testMethod->file);
            }

            $data['file'] = $request->file('file')
                ->store('test_methods', 'public');
        } else {
            // cegah file lama ketimpa null
            unset($data['file']);
        }

        $data['updated_by'] = auth()->user()->name;

        $testMethod->update($data);

        return response()->json($testMethod->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TestMethod $testMethod)
    {
        if ($testMethod->file) {
            Storage::disk('public')->delete($testMethod->file);
        }

        $testMethod->delete();

        return response()->noContent();
    }
}
