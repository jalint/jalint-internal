<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerContactRequest;
use App\Http\Requests\UpdateCustomerContactRequest;
use App\Models\CustomerContact;
use Illuminate\Http\JsonResponse;

class CustomerContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $perPage = (int) request()->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // guardrail: 1–100

        $search = request()->input('search');

        $query = CustomerContact::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
                // tambahkan kolom lain bila perlu
                // ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $customerContacts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($customerContacts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerContactRequest $request)
    {
        $customerContact = CustomerContact::create($request->validated());

        return response()->json($customerContact, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(CustomerContact $customerContact)
    {
        return response()->json($customerContact);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerContactRequest $request, CustomerContact $customerContact)
    {
        $customerContact->update($request->validated());

        return response()->json($customerContact->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CustomerContact $customerContact)
    {
        $customerContact->delete();

        return response()->noContent();
    }
}
