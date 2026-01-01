<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSampleTypeRequest;
use App\Http\Requests\UpdateSampleTypeRequest;
use App\Models\SampleType;
use Symfony\Component\HttpFoundation\JsonResponse;

class SampleTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $perPage = (int) request()->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // guardrail: 1–100

        $search = request()->input('search');

        $query = SampleType::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
                // tambahkan kolom lain bila perlu
                // ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $sampleType = $query->orderByDesc('created_at')->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($sampleType);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSampleTypeRequest $request)
    {
        $sampelType = SampleType::create($request->validated());

        return response()->json($sampelType, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(SampleType $sampleType)
    {
        return response()->json($sampleType);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSampleTypeRequest $request, SampleType $sampleType)
    {
        $sampleType->update($request->validated());

        return response()->json($sampleType->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SampleType $sampleType)
    {
        $sampleType->delete();

        return response()->noContent();
    }
}
