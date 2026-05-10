<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(['data' => User::all()]);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'username' => 'required|string|min:3|max:255|unique:users,username',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:guru,siswa',
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email',
        ]);

        $user = User::create([
            'username' => $payload['username'],
            'password' => Hash::make($payload['password']),
            'role' => $payload['role'],
            'name' => $payload['name'] ?? null,
            'email' => $payload['email'] ?? null,
        ]);

        if ($user->role === 'siswa') {
            Siswa::create(['user_id' => $user->id, 'nis' => $user->username]);
        }

        if ($user->role === 'guru') {
            Guru::create(['user_id' => $user->id, 'nik' => $user->username]);
        }

        return response()->json([
            'message' => 'User berhasil dibuat',
            'data' => $user,
        ], 201);
    }
}
