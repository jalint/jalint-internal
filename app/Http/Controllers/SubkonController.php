<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubkonRequest;
use App\Http\Requests\UpdateSubkonRequest;
use App\Models\Subkon;
use Illuminate\Http\JsonResponse;

class SubkonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $perPage = (int) request()->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // guardrail: 1–100

        $search = request()->input('search');

        $query = Subkon::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
                // tambahkan kolom lain bila perlu
                // ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $subkons = $query->orderByDesc('created_at')->simplePaginate($perPage);

        return response()->json($subkons);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSubkonRequest $request)
    {
        $subkon = Subkon::create($request->validated());

        return response()->json($subkon, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Subkon $subkon)
    {
        return response()->json($subkon);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSubkonRequest $request, Subkon $subkon)
    {
        $subkon->update($request->validated());

        return response()->json($subkon->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subkon $subkon)
    {
        $subkon->delete();

        return response()->noContent();
    }
}
