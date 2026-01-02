<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTestParameterRequest;
use App\Http\Requests\UpdateTestParameterRequest;
use App\Models\SampleType;
use App\Models\TestParameter;
use Symfony\Component\HttpFoundation\JsonResponse;

class TestParameterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $perPage = (int) request()->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $search = request()->input('search');
        $query = TestParameter::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('unit', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $testParameters = $query->with('testMethod:id,name', 'sampleType.regulation')->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($testParameters);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTestParameterRequest $request)
    {
        $testMethod = TestParameter::create($request->validated());

        return response()->json($testMethod, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TestParameter $testParameter)
    {
        return response()->json($testParameter->load('testMethod:id,name'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTestParameterRequest $request, TestParameter $testParameter): JsonResponse
    {
        $testParameter->update($request->validated());

        return response()->json($testParameter->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TestParameter $testParameter)
    {
        $testParameter->delete();

        return response()->noContent();
    }

    public function listGroupedTestParameters(?string $search = null)
    {
        $data = SampleType::query()
            ->whereHas('testParameters', function ($query) use ($search) {
                if ($search) {
                    $query->where('name', 'like', '%'.$search.'%');
                }
            })
            ->with([
                'regulation',
                'testParameters' => function ($query) use ($search) {
                    if ($search) {
                        $query->where('name', 'like', '%'.$search.'%');
                    }

                    $query->orderBy('name');
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($sampleType) {
                return [
                    'sample_type_id' => $sampleType->id,
                    'sample_type_name' => $sampleType->name,

                    'regulation' => $sampleType->regulation ? [
                        'id' => $sampleType->regulation->id,
                        'name' => $sampleType->regulation->name,
                        'code' => $sampleType->regulation->code ?? null,
                    ] : null,

                    'parameters' => $sampleType->testParameters->map(function ($param) {
                        return [
                            'id' => $param->id,
                            'name' => $param->name,
                            'code' => $param->code,
                            'unit' => $param->unit,
                            'price' => $param->price,
                        ];
                    })->values(),
                ];
            })
            ->values();

        return response()->json($data);
    }
}
