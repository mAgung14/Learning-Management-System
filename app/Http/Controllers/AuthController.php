<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\User;
use App\Models\Siswa;
use App\Models\Rombel;
use App\Models\AnggotaKelas;
use App\Models\Jurusan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function registerForm()
    {
        try {
            $rombel = Rombel::with([
                'kelas:id,tingkat',
                'jurusan:id,nama_jurusan'
            ])
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'nama_rombel' => trim(($r->kelas->tingkat ?? '') . ' ' . ($r->jurusan->nama_jurusan ?? ''))
                    ];
                });
            $jurusan = Jurusan::select('id', 'nama_jurusan')->get();
            $kelas = Kelas::select('id', 'tingkat')->get();
            return response()->json([
                'success' => true,
                'data' => [
                    'rombel' => $rombel,
                    'jurusan' => $jurusan,
                    'kelas' => $kelas
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data form',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function register(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|min:3|max:255|unique:users,username',
            'password' => 'required|string|min:6|confirmed',
            'nama' => 'required|string|max:255',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'role' => 'nullable|in:guru,siswa',
            'rombel_id' => 'required_if:role,siswa|exists:rombel,id',
        ]);

        DB::beginTransaction();

        try {

            // 🔥 create user
            $user = User::create([
                'username' => $validated['username'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'] ?? 'siswa',
            ]);

            // 🔥 SISWA
            if ($user->role === 'siswa') {
                $rombel = Rombel::find($validated['rombel_id']);

                $siswa = Siswa::create([
                    'user_id' => $user->id,
                    'nis' => $user->username,
                    'nama' => $validated['nama'],
                    'jenis_kelamin' => $validated['jenis_kelamin'],
                    'jurusan_id' => $rombel ? $rombel->jurusan_id : null,
                ]);

                AnggotaKelas::create([
                    'siswa_id' => $siswa->id,
                    'rombel_id' => $validated['rombel_id'],
                ]);

            }

            // 🔥 GURU
            if ($user->role === 'guru') {
                Guru::create([
                    'user_id' => $user->id,
                    'nik' => $user->username,
                    'nama' => $validated['nama'],
                    'jenis_kelamin' => $validated['jenis_kelamin'],
                ]);
            }

            // JWT
            $token = JWTAuth::fromUser($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ],
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        try {
            // Validasi input
            $validated = $request->validate([
                'username' => 'required|string',
                'password' => 'required|string|min:6',
            ], [
                'username.required' => 'Username harus diisi',
                'password.required' => 'Password harus diisi',
                'password.min' => 'Password minimal 6 karakter',
            ]);

            // Cari user berdasarkan username
            $user = User::where('username', $validated['username'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username atau password salah',
                ], 401);
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'role' => $user->role,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token error: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login gagal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data user berhasil diambil',
                'data' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'role' => $user->role,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        try {
            JWTAuth::parseToken()->invalidate();

            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil',
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout gagal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request)
    {
        try {
            $token = JWTAuth::parseToken()->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Token berhasil diperbarui',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ],
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token gagal: ' . $e->getMessage(),
            ], 500);
        }
    }
}