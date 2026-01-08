<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyBankAccountRequest;
use App\Http\Requests\UpdateCompanyBankAccountRequest;
use App\Models\CompanyBankAccount;
use Illuminate\Http\Request;

class CompanyBankAccountController extends Controller
{
    public function index(Request $request)
    {
        $query = CompanyBankAccount::query()
            ->orderByDesc('is_active')
            ->orderBy('bank_name');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('bank_name', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%")
                  ->orWhere('account_name', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->paginate($request->per_page ?? 15)
        );
    }

    public function store(StoreCompanyBankAccountRequest $request)
    {
        $account = CompanyBankAccount::create($request->validated());

        return response()->json($account, 201);
    }

    public function show($id)
    {
        $account = CompanyBankAccount::findOrFail($id);

        return response()->json($account);
    }

    public function update(UpdateCompanyBankAccountRequest $request, $id)
    {
        $account = CompanyBankAccount::findOrFail($id);

        $account->update($request->validated());

        return response()->json($account);
    }

    public function destroy($id)
    {
        $account = CompanyBankAccount::findOrFail($id);

        // hard rule: jangan hapus kalau sudah dipakai pembayaran
        if ($account->invoicePayments()->exists()) {
            abort(400, 'Rekening tidak dapat dihapus karena sudah digunakan');
        }

        $account->delete();

        return response()->json([
            'message' => 'Rekening perusahaan berhasil dihapus',
        ]);
    }
}
