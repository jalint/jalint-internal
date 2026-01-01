<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSampleMatrixRequest;
use App\Http\Requests\UpdateSampleMatrixRequest;
use App\Models\SampleMatrix;
use Illuminate\Http\JsonResponse;

class SampleMatrixController extends Controller
{
    public function index(): JsonResponse
    {
        $perPage = (int) request()->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $search = request()->input('search');

        $query = SampleMatrix::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sampleMatrix = $query->with('sampleType:id,name')->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($sampleMatrix);
    }

    public function store(StoreSampleMatrixRequest $request): JsonResponse
    {
        $sampleMatrix = SampleMatrix::create($request->validated());

        return response()->json($sampleMatrix, 201);
    }

    public function show(SampleMatrix $sampleMatrix): JsonResponse
    {
        return response()->json($sampleMatrix->load('sampleType:id,name'));
    }

    public function update(UpdateSampleMatrixRequest $request, SampleMatrix $sampleMatrix): JsonResponse
    {
        $sampleMatrix->update($request->validated());

        return response()->json($sampleMatrix->fresh());
    }

    public function destroy(SampleMatrix $sampleMatrix)
    {
        $sampleMatrix->delete();

        return response()->noContent();
    }
}
