<?php

namespace App\Http\Controllers;

use App\Events\PengumumanCreated;
use App\Models\Pengumuman;
use App\Models\Rombel;
use App\Models\AnggotaKelas;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PengumumanController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Pengumuman::with(['user:id,username,role', 'mapel:id,nama_mapel']);

        if ($request->filled('mapel_id')) {
            $query->where('mapel_id', $request->mapel_id);
        }

        if ($user && $user->role === 'siswa') {
            $siswa = $user->siswa;
            $anggotaKelasIds = $siswa?->anggotaKelas()->pluck('anggota_kelas.id')->toArray() ?? [];
            $rombelIds = $siswa?->anggotaKelas()->pluck('rombel_id')->toArray() ?? [];

            $mapelIds = [];
            if ($rombelIds) {
                $mapelIds = DB::table('rombel_mapel')
                    ->whereIn('rombel_id', $rombelIds)
                    ->pluck('mata_pelajaran_id')
                    ->toArray();
            }

            $query->where(function ($sub) use ($anggotaKelasIds, $mapelIds) {
                if ($anggotaKelasIds) {
                    $sub->whereIn('anggota_kelas_id', $anggotaKelasIds);
                }
                if ($mapelIds) {
                    $sub->orWhereIn('mapel_id', $mapelIds);
                }
            });
        }

        if ($user && $user->role === 'guru') {
            $guru = $user->guru;
            if ($guru) {
                $mapelIds = $guru->mapel()->pluck('mata_pelajaran.id')->toArray();
                $rombelIds = [];
                if ($mapelIds) {
                    $rombelIds = DB::table('rombel_mapel')
                        ->whereIn('mata_pelajaran_id', $mapelIds)
                        ->pluck('rombel_id')
                        ->unique()
                        ->toArray();
                }

                $anggotaKelasIds = AnggotaKelas::whereIn('rombel_id', $rombelIds)->pluck('id')->toArray();

                $query->where(function ($sub) use ($user, $anggotaKelasIds) {
                    $sub->where('user_id', $user->id)
                        ->orWhereIn('anggota_kelas_id', $anggotaKelasIds);
                });
            }
        }

        return response()->json([
            'data' => $query->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'guru') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya guru yang bisa menambahkan pengumuman',
            ], 403);
        }

        $payload = $request->validate([
            'judul' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'mapel_id' => 'sometimes|nullable|exists:mata_pelajaran,id',
        ]);

        if (!$user->guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak ditemukan',
            ], 404);
        }

        $guru = $user->guru;
        $guruMapelIds = $guru->mapel()->pluck('mata_pelajaran.id')->toArray();

        if (empty($guruMapelIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Guru ini belum memiliki mata pelajaran yang diajar',
            ], 422);
        }

        // Jika mapel_id tidak disediakan, otomatis isi dengan mapel pertama yang guru ajar
        if (empty($payload['mapel_id'])) {
            $payload['mapel_id'] = $guruMapelIds[0];
        } elseif (!in_array((int) $payload['mapel_id'], $guruMapelIds, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Mata pelajaran tidak diajar oleh guru ini',
            ], 403);
        }

        // Jika anggota_kelas_id disediakan, pastikan guru mengajar di rombel itu
        if (!empty($payload['anggota_kelas_id'])) {
            $anggotaKelas = AnggotaKelas::find($payload['anggota_kelas_id']);
            if (!$anggotaKelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anggota kelas tidak ditemukan',
                ], 404);
            }

            $rombel = $anggotaKelas->rombel;
            $rombelMapelIds = $rombel->mataPelajaran()->pluck('mata_pelajaran.id')->toArray();
            if (!array_intersect($guruMapelIds, $rombelMapelIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guru ini tidak mengajar rombel yang dipilih',
                ], 403);
            }

            if (!empty($payload['mapel_id']) && !in_array((int) $payload['mapel_id'], $rombelMapelIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata pelajaran tidak ada di rombel yang dipilih',
                ], 403);
            }
        }

        $pengumuman = Pengumuman::create([
            'judul' => $payload['judul'],
            'deskripsi' => $payload['deskripsi'],
            'user_id' => $user->id,
            'mapel_id' => $payload['mapel_id'] ?? null,
        ]);

        PengumumanCreated::dispatch($pengumuman);

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman berhasil dibuat',
            'data' => $pengumuman,
        ], 201);
    }

    public function show($id)
    {
        return response()->json([
            'data' => Pengumuman::with(['user:id,username,role', 'mapel:id,nama_mapel'])->findOrFail($id),
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'guru') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya guru yang bisa memperbarui pengumuman',
            ], 403);
        }

        $pengumuman = Pengumuman::findOrFail($id);

        if ($pengumuman->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya guru yang membuat pengumuman ini yang bisa memperbarui',
            ], 403);
        }

        $payload = $request->validate([
            'judul' => 'sometimes|string|max:255',
            'deskripsi' => 'sometimes|string',
            'mapel_id' => 'sometimes|nullable|exists:mata_pelajaran,id',
        ]);

        if (!$user->guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak ditemukan',
            ], 404);
        }

        $guru = $user->guru;
        $guruMapelIds = $guru->mapel()->pluck('mata_pelajaran.id')->toArray();

        if (empty($guruMapelIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Guru ini belum memiliki mata pelajaran yang diajar',
            ], 422);
        }

        // Jika mapel_id disediakan, validasi
        if (array_key_exists('mapel_id', $payload)) {
            if (!empty($payload['mapel_id']) && !in_array((int) $payload['mapel_id'], $guruMapelIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata pelajaran tidak diajar oleh guru ini',
                ], 403);
            }
        }

        // Jika anggota_kelas_id disediakan, validasi
        if (array_key_exists('anggota_kelas_id', $payload) && !empty($payload['anggota_kelas_id'])) {
            $anggotaKelas = AnggotaKelas::find($payload['anggota_kelas_id']);
            if (!$anggotaKelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anggota kelas tidak ditemukan',
                ], 404);
            }

            $rombel = $anggotaKelas->rombel;
            $rombelMapelIds = $rombel->mataPelajaran()->pluck('mata_pelajaran.id')->toArray();
            if (!array_intersect($guruMapelIds, $rombelMapelIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guru ini tidak mengajar rombel yang dipilih',
                ], 403);
            }

            $currentMapelId = array_key_exists('mapel_id', $payload) ? $payload['mapel_id'] : $pengumuman->mapel_id;
            if (!empty($currentMapelId) && !in_array((int) $currentMapelId, $rombelMapelIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata pelajaran tidak ada di rombel yang dipilih',
                ], 403);
            }
        }

        $pengumuman->update([
            'judul' => $payload['judul'] ?? $pengumuman->judul,
            'deskripsi' => $payload['deskripsi'] ?? $pengumuman->deskripsi,
            'mapel_id' => array_key_exists('mapel_id', $payload) ? $payload['mapel_id'] : $pengumuman->mapel_id,
            'anggota_kelas_id' => array_key_exists('anggota_kelas_id', $payload) ? $payload['anggota_kelas_id'] : $pengumuman->anggota_kelas_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman berhasil diperbarui',
            'data' => $pengumuman,
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'guru') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya guru yang bisa menghapus pengumuman',
            ], 403);
        }

        $pengumuman = Pengumuman::findOrFail($id);
        if ($pengumuman->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya guru yang membuat pengumuman ini yang bisa menghapus',
            ], 403);
        }

        Pengumuman::destroy($id);

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman berhasil dihapus',
        ]);
    }
}
