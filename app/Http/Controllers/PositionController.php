<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Models\Position;
use Illuminate\Http\JsonResponse;

class PositionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $perPage = (int) request()->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // guardrail: 1–100

        $search = request()->input('search');

        $query = Position::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $postions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($postions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePositionRequest $request)
    {
        $request->merge(['created_by' => auth()->user()->name]);
        $position = Position::create($request->all());

        return response()->json($position, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Position $position)
    {
        return response()->json($position);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePositionRequest $request, Position $position)
    {
        $request->merge(['updated_by' => auth()->user()->name]);
        $position->update($request->all());

        return response()->json($position->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Position $position)
    {
        $position->delete();

        return response()->noContent();
    }
}
