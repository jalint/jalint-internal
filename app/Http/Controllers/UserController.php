<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // GET /api/users
    public function index(Request $request)
    {
        $perPage = (int) request()->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // guardrail: 1–100
        $query = User::with('roles:id,name');

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('roles', function ($r) use ($search) {
                      $r->where('name', 'like', "%{$search}%");
                  });
            });
        }

        return response()->json(
            $query->orderBy('created_at', 'desc')->paginate($perPage)
        );
    }

    // POST /api/users
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'role' => 'required',
            'status' => 'required|boolean',
        ]);

        $photoPath = $request->file('photo')->store('users', 'public');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'photo_path' => $photoPath,
            'status' => $validated['status'],
        ]);

        $user->assignRole($validated['role']);

        return response()->json($user->load('roles'), 201);
    }

    // GET /api/users/{id}
    public function show($id)
    {
        $user = User::findOrFail($id);

        return response()->json($user, 200);
    }

    // PUT /api/users/{id}
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,'.$user->id,
            'password' => 'nullable|min:8',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'role' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        if ($request->hasFile('photo')) {
            if ($user->photo_path) {
                Storage::disk('public')->delete($user->photo_path);
            }
            $validated['photo'] = $request->file('photo')->store('users', 'public');
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        if (!empty($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return response()->json($user->load('roles:id,name'));
    }

    // DELETE /api/users/{id}
    public function destroy(User $user)
    {
        // $user->delete();
        $user->update(['status' => 0]);

        return response()->json(['message' => 'user berhasil dinonaktifkan']);
    }
}
