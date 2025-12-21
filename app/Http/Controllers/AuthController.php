<?php

namespace App\Http\Controllers;

use App\Models\CustomerAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])
        ->where('status', 1)
        ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah',
            ], 401);
        }

        // $user->assignRole('admin');

        // Generate Sanctum Tokens
        $token = $user->createToken('auth_token')->plainTextToken;

        // Ambil permission dari Spatie
        // $permissions = $user->getAllPermissions()->pluck('name');

        return response()->json([
            'token_type' => 'Bearer',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                // 'permissions' => $permissions,
            ],
        ]);
    }

    public function loginCustomer(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $customer = CustomerAccount::where('email', $credentials['email'])->first();

        if (!$customer || !Hash::check($credentials['password'], $customer->password)) {
            return response()->json([
                'message' => 'Email atau password salah',
            ], 401);
        }

        $token = $customer->createToken('customer_token')->plainTextToken;

        return response()->json([
            'token_type' => 'Bearer',
            'token' => $token,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'roles' => $customer->getRoleNames(),
            ],
        ]);
    }
}
