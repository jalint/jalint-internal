<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegulationRequest;
use App\Http\Requests\UpdateRegulationRequest;
use App\Models\Regulation;
use Illuminate\Http\JsonResponse;

class RegulationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $perPage = (int) request()->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $search = request()->input('search');

        $query = Regulation::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $regulations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($regulations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRegulationRequest $request)
    {
        $sampelType = Regulation::create($request->validated());

        return response()->json($sampelType, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Regulation $regulation)
    {
        return response()->json($regulation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRegulationRequest $request, Regulation $regulation): JsonResponse
    {
        $regulation->update($request->validated());

        return response()->json($regulation->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Regulation $regulation)
    {
        $regulation->delete();

        return response()->noContent();
    }
}
