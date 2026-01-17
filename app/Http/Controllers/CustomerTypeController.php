<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerTypeRequest;
use App\Http\Requests\UpdateCustomerTypeRequest;
use App\Models\CustomerType;
use Illuminate\Http\JsonResponse;

class CustomerTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $perPage = (int) request()->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // guardrail: 1–100

        $search = request()->input('search');

        $query = CustomerType::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
                // tambahkan kolom lain bila perlu
                // ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $customerTypes = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($customerTypes);
    }

    public function store(StoreCustomerTypeRequest $request): JsonResponse
    {
        $request->merge(['created_by' => auth()->user()->name]);
        $type = CustomerType::create($request->all());

        return response()->json($type, 201);
    }

    public function show(CustomerType $type): JsonResponse
    {
        return response()->json($type);
    }

    public function update(UpdateCustomerTypeRequest $request, CustomerType $type): JsonResponse
    {
        $request->merge(['updated_by' => auth()->user()->name]);
        $type->update($request->all());

        return response()->json($type->fresh());
    }

    public function destroy(CustomerType $type)
    {
        $type->delete();

        return response()->noContent();
    }
}
