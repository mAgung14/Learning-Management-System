<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuruController extends Controller
{
    public function index()
    {
         $data = Guru::select('id', 'nama', 'nik', 'jenis_kelamin')
        ->get();

    return response()->json([
        'data' => $data->map(function ($item) {
            return [
                'id' => $item->id,
                'nama' => $item->nama,
                'nik' => $item->nik,
                'jenis_kelamin' => $item->jenis_kelamin,
            ];
        })
    ]);
    }

    public function show($id)
    {
        return response()->json([
            'data' => Guru::with('user')->findOrFail($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $guru = Guru::findOrFail($id);

        $payload = $request->validate([
            'nik' => 'sometimes|string|max:255',
            'nama' => 'sometimes|string|max:255',
            'mapel_id' => 'sometimes|exists:mata_pelajaran,id',
        ]);

        $guru->update($payload);

        return response()->json([
            'message' => 'Data guru berhasil diupdate',
            'data' => $guru->load('mapel:id,nama_mapel'),
        ]);
    }

    public function getProfile()
    {
        $user = auth('api')->user();
        if ($user->role !== 'guru') {
            return response()->json(['message' => 'Hanya guru yang dapat melihat profile'], 403);
        }

        $guru = Guru::where('user_id', $user->id)->with('user:id,username,role')->first();
        if (!$guru) {
            return response()->json(['message' => 'Data guru tidak ditemukan'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $guru->id,
                'nama' => $guru->nama,
                'nik' => $guru->nik,
                'jenis_kelamin' => $guru->jenis_kelamin,
                'username' => $guru->user->username,
                'role' => $guru->user->role,
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth('api')->user();
        if ($user->role !== 'guru') {
            return response()->json(['message' => 'Hanya guru yang dapat mengupdate profile'], 403);
        }

        $guru = Guru::where('user_id', $user->id)->first();
        if (!$guru) {
            return response()->json(['message' => 'Data guru tidak ditemukan'], 404);
        }

        $payload = $request->validate([
            'nama' => 'sometimes|string|max:255',
            'nik' => 'sometimes|string|max:255',
            'jenis_kelamin' => 'sometimes|in:L,P',
        ]);

        $guru->update($payload);

        return response()->json([
            'message' => 'Profile guru berhasil diupdate',
            'data' => $guru,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = auth('api')->user();
        if ($user->role !== 'guru') {
            return response()->json(['message' => 'Hanya guru yang dapat mengupdate password'], 403);
        }

        $payload = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!\Hash::check($payload['current_password'], $user->password)) {
            return response()->json(['message' => 'Password lama tidak cocok'], 400);
        }

        $user->update([
            'password' => \Hash::make($payload['password']),
        ]);

        return response()->json([
            'message' => 'Password berhasil diupdate',
        ]);
    }

    public function destroy($id)
    {
        $guru = Guru::findOrFail($id);

        DB::transaction(function () use ($guru) {
            if ($guru->user_id) {
                \App\Models\User::destroy($guru->user_id);
            } else {
                $guru->delete();
            }
        });

        return response()->json([
            'message' => 'Data guru berhasil dihapus'
        ]);
    }

    public function mataPelajaran()
    {
        $user = auth('api')->user();

        if ($user->role !== 'guru') {
            return response()->json(['message' => 'Unauthorized. Hanya untuk guru.'], 403);
        }

        $guru = Guru::where('user_id', $user->id)->first();
        if (!$guru) {
            return response()->json(['message' => 'Data guru tidak ditemukan.'], 404);
        }

        $mapels = $guru->mapel()->with(['rombel.kelas', 'rombel.jurusan'])->get();

        $data = $mapels->flatMap(function ($m) {
            return $m->rombel->map(function ($rombel) use ($m) {
                // Menghitung total siswa di rombel yang terkait dengan mapel ini
                $jumlahSiswa = DB::table('anggota_kelas')
                    ->where('rombel_id', $rombel->id)
                    ->count();

                $tingkat = $rombel->kelas->tingkat ?? '';
                $namaJurusan = $rombel->jurusan->nama_jurusan ?? '';
                $tahunAjaran = $rombel->kelas->tahun_ajaran ?? '';

                return [
                    'id' => $m->id,
                    'rombel_id' => $rombel->id,
                    'nama_mapel' => $m->nama_mapel,
                    'tahun_ajaran' => $tahunAjaran,
                    'nama_kelas' => trim($tingkat . ' ' . $namaJurusan),
                    'jumlah_siswa' => $jumlahSiswa,
                ];
            });
        })->values();

        return response()->json([
            'data' => $data
        ]);
    }

    public function resetPassword($id)
    {
        $guru = Guru::findOrFail($id);

        $user = $guru->user;
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Akun user untuk guru ini tidak ditemukan.'
            ], 404);
        }

        $user->update([
            'password' => \Hash::make('12345678')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password guru berhasil direset ke "12345678".'
        ]);
    }
}