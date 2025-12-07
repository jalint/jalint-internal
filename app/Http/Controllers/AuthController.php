<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Email atau password salah',
            ], 401);
        }

        $user = Auth::user();
        // $user->assignRole('admin');

        // Generate Sanctum Tokens
        $token = $user->createToken('auth_token')->plainTextToken;

        // Ambil permission dari Spatie
        // $permissions = $user->getAllPermissions()->pluck('name');

        return response()->json([
            'success' => true,
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                // 'permissions' => $permissions,
            ],
        ]);
    }
}
