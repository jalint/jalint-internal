<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTestPackageRequest;
use App\Models\TestPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestPackageController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);
        $search = $request->string('search')->toString();

        $query = TestPackage::query()
            ->with([
                'testParameters:id,name',
                'sampleMatrix:id,name',
                'regulation:id,name',
            ]);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $packages = $query
            ->orderBy('created_at', 'desc')
            ->simplePaginate($perPage);

        return response()->json($packages);
    }

    public function store(StoreTestPackageRequest $request)
    {
        $package = DB::transaction(function () use ($request) {
            $package = TestPackage::create([
                'name' => $request->name,
                'price' => $request->price,
                'sample_matrix_id' => $request->sample_matrix_id,
                'regulation_id' => $request->regulation_id,
                'description' => $request->description,
                'is_active' => $request->is_active,
            ]);

            // attach parameter ke pivot
            $package->testParameters()->attach($request->test_parameters);

            return $package;
        });

        return response()->json($package, 201);
    }

    public function update(Request $request, TestPackage $testPackage)
    {
        DB::transaction(function () use ($request, $testPackage) {
            $testPackage->update([
                'name' => $request->name,
                'price' => $request->price,
                'sample_matrix_id' => $request->sample_matrix_id,
                'regulation_id' => $request->regulation_id,
                'description' => $request->description,
                'is_active' => $request->is_active,
            ]);

            // sync = update pivot tanpa duplikasi
            $testPackage->testParameters()->sync($request->test_parameters);
        });

        return response()->json($testPackage->fresh());
    }

    public function show(TestPackage $testPackage)
    {
        return response()->json(
            $testPackage->load(['testParameters', 'sampleMatrix:id,name', 'regulation:id,name']),
        );
    }

    public function destroy(TestPackage $testPackage)
    {
        $testPackage->delete();

        return response()->noContent();
    }
}
