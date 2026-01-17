<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCertificationRequest;
use App\Models\Certification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CertificationController extends Controller
{
    public function index()
    {
        $perPage = request()->integer('per_page', 10);
        $search = request()->string('search')->toString();

        $query = Certification::query()
            ->with([
                'testParameters:id,name',
            ]);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $packages = $query
            ->orderBy('created_at', 'desc')
            ->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($packages);
    }

    public function store(StoreCertificationRequest $request)
    {
        $result = DB::transaction(function () use ($request) {
            $certification = Certification::create([
                'name' => $request->name,
                'description' => $request->description,
                'created_by' => auth()->user()->name,
            ]);

            // attach parameter ke pivot
            $certification->testParameters()->attach($request->test_parameters);

            return $certification;
        });

        return response()->json($result, 201);
    }

    public function update(Request $request, Certification $certification)
    {
        DB::transaction(function () use ($request, $certification) {
            $certification->update([
                'name' => $request->name,
                'description' => $request->description,
                'updated_by' => auth()->user()->name,
            ]);

            // sync = update pivot tanpa duplikasi
            $certification->testParameters()->sync($request->test_parameters);
        });

        return response()->json($certification->fresh());
    }

    public function show(Certification $certification)
    {
        return response()->json(
            $certification->load(['testParameters:id,name']),
        );
    }

    public function destroy(Certification $certification)
    {
        $certification->delete();

        return response()->noContent();
    }
}
