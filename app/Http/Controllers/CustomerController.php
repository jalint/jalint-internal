<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\JsonResponse;

class CustomerController extends Controller
{
    public function index(): JsonResponse
    {
        $perPage = (int) request()->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $search = request()->input('search');

        $query = Customer::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $customers = $query->with('customerType:id,name', 'customerContact')->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($customers);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = DB::transaction(function () use ($request) {
            $customer = Customer::create($request->validated());

            $account = CustomerAccount::create([
                'customer_id' => $customer->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt('jalint2025')]);

            $account->assignRole('customer');

            CustomerContact::create([
                'customer_id' => $customer->id,
                'name' => $request->pic_name,
                'position' => $request->pic_position,
                'email' => $request->pic_email,
                'phone' => $request->pic_phone,
                'npwp' => $request->pic_npwp,
            ]);

            return $customer;
        });

        return response()->json($customer, 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($customer->load(['customerType:id,name', 'customerContact']));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        if ($customer->customerAccount) {
            $customer->customerAccount->update([
                'name' => $customer->name,
                'email' => $customer->email,
            ]);
        }

        CustomerContact::where('customer_id', $customer->id)->update([
            'name' => $request->pic_name,
            'position' => $request->pic_position,
            'email' => $request->pic_email,
            'phone' => $request->pic_phone,
            'npwp' => $request->pic_npwp,
        ]);

        return response()->json($customer->fresh());
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->noContent();
    }

    public function resetPassword(Request $request, $id): JsonResponse
    {
        // Authorization (wajib, jangan ditunda)
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'forbidden'], 403);
        }

        // Validasi input
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Ambil user atau 404
        $customer = Customer::findOrFail($id);

        $user = CustomerAccount::where('customer_id', $customer->id)->first();

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // $user->tokens()->delete();

        return response()->json([
            'message' => 'Password berhasil direset oleh admin.',
        ], 200);
    }
}
