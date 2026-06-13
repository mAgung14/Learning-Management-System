<?php

namespace App\Http\Controllers;

use App\Models\Tugas;
use App\Models\Siswa;
use App\Models\TugasSusulan;
use Illuminate\Http\Request;

class TugasSusulanController extends Controller
{
    /**
     * Tampilkan daftar tugas susulan (untuk Guru).
     *
     * Menampilkan daftar seluruh tugas susulan yang dikelola oleh guru yang sedang login.
     * Dapat difilter berdasarkan tugas_id atau siswa_id.
     */
    public function index(Request $request)
    {
        $user = auth('api')->user();

        if (!$user || $user->role !== 'guru') {
            return response()->json(['message' => 'Unauthorized. Hanya guru yang dapat melihat tugas susulan.'], 403);
        }

        $guru = $user->guru;
        if (!$guru) {
            return response()->json(['message' => 'Data guru tidak ditemukan.'], 404);
        }

        $query = TugasSusulan::with(['tugas.mapel', 'siswa'])
            ->whereHas('tugas', function ($q) use ($guru) {
                $q->where('guru_id', $guru->id);
            });

        if ($request->has('tugas_id')) {
            $query->where('tugas_id', $request->tugas_id);
        }

        if ($request->has('siswa_id')) {
            $query->where('siswa_id', $request->siswa_id);
        }

        $tugasSusulan = $query->latest()->get()->map(function ($susulan) {
            $pengumpulan = \App\Models\Pengumpulan::with('nilai')
                ->where('tugas_id', $susulan->tugas_id)
                ->where('siswa_id', $susulan->siswa_id)
                ->first();

            $status = 'Belum dikumpulkan';
            if ($pengumpulan) {
                $status = 'Sudah dikumpulkan';
            } else if (\Carbon\Carbon::parse($susulan->deadline)->isPast()) {
                $status = 'Terlambat';
            }

            return [
                'id' => $susulan->id,
                'tugas_id' => $susulan->tugas_id,
                'siswa_id' => $susulan->siswa_id,
                'nama_siswa' => $susulan->siswa->nama,
                'judul_tugas' => $susulan->judul ?? ('Susulan: ' . $susulan->tugas->judul),
                'deskripsi_tugas' => $susulan->deskripsi ?? $susulan->tugas->deskripsi,
                'nama_mapel' => $susulan->tugas->mapel->nama_mapel ?? null,
                'deadline_susulan' => $susulan->deadline,
                'keterangan' => $susulan->keterangan,
                'status' => $status,
                'pengumpulan' => $pengumpulan ? [
                    'id' => $pengumpulan->id,
                    'file' => $pengumpulan->file,
                    'link' => $pengumpulan->link,
                    'submitted_at' => $pengumpulan->submitted_at,
                    'nilai' => $pengumpulan->nilai?->score ?? 'Belum dinilai'
                ] : null
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $tugasSusulan
        ]);
    }

    /**
     * Berikan tugas susulan ke siswa (untuk Guru).
     *
     * Membuat atau memperbarui tugas susulan untuk siswa tertentu pada tugas tertentu dengan tenggat waktu baru.
     * Dapat ditambahkan judul susulan dan deskripsi susulan baru jika tugas susulannya berbeda.
     */
    public function store(Request $request)
    {
        $user = auth('api')->user();

        if (!$user || $user->role !== 'guru') {
            return response()->json(['message' => 'Unauthorized. Hanya guru yang dapat memberikan tugas susulan.'], 403);
        }

        $guru = $user->guru;
        if (!$guru) {
            return response()->json(['message' => 'Data guru tidak ditemukan.'], 404);
        }

        $payload = $request->validate([
            'tugas_id' => 'required|exists:tugas,id',
            'siswa_id' => 'required|exists:siswa,id',
            'deadline' => 'required|date',
            'judul' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'keterangan' => 'nullable|string',
        ]);

        $tugas = Tugas::find($payload['tugas_id']);
        if ($tugas->guru_id !== $guru->id) {
            return response()->json(['message' => 'Akses ditolak. Anda bukan pembuat tugas ini.'], 403);
        }

        $tugasSusulan = TugasSusulan::updateOrCreate(
            [
                'tugas_id' => $payload['tugas_id'],
                'siswa_id' => $payload['siswa_id'],
            ],
            [
                'judul' => $payload['judul'] ?? null,
                'deskripsi' => $payload['deskripsi'] ?? null,
                'deadline' => $payload['deadline'],
                'keterangan' => $payload['keterangan'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Tugas susulan berhasil diberikan.',
            'data' => $tugasSusulan->load(['tugas', 'siswa'])
        ], 201);
    }

    /**
     * Batalkan tugas susulan (untuk Guru).
     *
     * Menghapus tugas susulan berdasarkan ID yang membatalkan pemberian tugas susulan tersebut.
     */
    public function destroy($id)
    {
        $user = auth('api')->user();

        if (!$user || $user->role !== 'guru') {
            return response()->json(['message' => 'Unauthorized. Hanya guru yang dapat menghapus tugas susulan.'], 403);
        }

        $guru = $user->guru;
        if (!$guru) {
            return response()->json(['message' => 'Data guru tidak ditemukan.'], 404);
        }

        $tugasSusulan = TugasSusulan::with('tugas')->find($id);

        if (!$tugasSusulan) {
            return response()->json(['message' => 'Tugas susulan tidak ditemukan.'], 404);
        }

        if ($tugasSusulan->tugas->guru_id !== $guru->id) {
            return response()->json(['message' => 'Akses ditolak. Anda tidak berwenang menghapus tugas susulan ini.'], 403);
        }

        $tugasSusulan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tugas susulan berhasil dibatalkan.'
        ]);
    }

    /**
     * Tampilkan daftar tugas susulan siswa (untuk Siswa).
     *
     * Menampilkan daftar tugas susulan yang harus dikerjakan oleh siswa yang sedang login.
     */
    public function siswaIndex()
    {
        $user = auth('api')->user();

        if (!$user || $user->role !== 'siswa') {
            return response()->json(['message' => 'Unauthorized. Hanya siswa yang dapat melihat tugas susulan.'], 403);
        }

        $siswa = $user->siswa;
        if (!$siswa) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $tugasSusulanList = TugasSusulan::with([
            'tugas.mapel',
            'tugas.guru',
        ])
        ->where('siswa_id', $siswa->id)
        ->latest()
        ->get()
        ->map(function ($susulan) use ($siswa) {
            $pengumpulan = \App\Models\Pengumpulan::with('nilai')
                ->where('tugas_id', $susulan->tugas_id)
                ->where('siswa_id', $siswa->id)
                ->first();

            $status = 'Belum dikumpulkan';
            if ($pengumpulan) {
                $status = 'Sudah dikumpulkan';
            } else if (\Carbon\Carbon::parse($susulan->deadline)->isPast()) {
                $status = 'Terlambat';
            }

            return [
                'id' => $susulan->id,
                'tugas_id' => $susulan->tugas_id,
                'judul_tugas' => $susulan->judul ?? ('Susulan: ' . $susulan->tugas->judul),
                'deskripsi_tugas' => $susulan->deskripsi ?? $susulan->tugas->deskripsi,
                'nama_mapel' => $susulan->tugas->mapel->nama_mapel ?? null,
                'guru' => $susulan->tugas->guru->nama ?? null,
                'deadline_susulan' => $susulan->deadline,
                'original_deadline' => $susulan->tugas->deadline,
                'keterangan' => $susulan->keterangan,
                'status' => $status,
                'pengumpulan' => $pengumpulan ? [
                    'id' => $pengumpulan->id,
                    'file' => $pengumpulan->file,
                    'link' => $pengumpulan->link,
                    'submitted_at' => $pengumpulan->submitted_at,
                    'nilai' => $pengumpulan->nilai?->score ?? 'Belum dinilai'
                ] : null
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $tugasSusulanList
        ]);
    }

    /**
     * Tampilkan detail tugas susulan siswa (untuk Siswa).
     *
     * Menampilkan informasi detail satu tugas susulan berdasarkan ID.
     */
    public function siswaShow($id)
    {
        $user = auth('api')->user();

        if (!$user || $user->role !== 'siswa') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $siswa = $user->siswa;
        if (!$siswa) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $susulan = TugasSusulan::with([
            'tugas.mapel',
            'tugas.guru',
        ])
        ->where('siswa_id', $siswa->id)
        ->find($id);

        if (!$susulan) {
            return response()->json(['message' => 'Tugas susulan tidak ditemukan atau Anda tidak memiliki akses.'], 404);
        }

        $pengumpulan = \App\Models\Pengumpulan::with('nilai')
            ->where('tugas_id', $susulan->tugas_id)
            ->where('siswa_id', $siswa->id)
            ->first();

        $status = 'Belum dikumpulkan';
        if ($pengumpulan) {
            $status = 'Sudah dikumpulkan';
        } else if (\Carbon\Carbon::parse($susulan->deadline)->isPast()) {
            $status = 'Terlambat';
        }

        $data = [
            'id' => $susulan->id,
            'tugas_id' => $susulan->tugas_id,
            'judul_tugas' => $susulan->judul ?? ('Susulan: ' . $susulan->tugas->judul),
            'deskripsi_tugas' => $susulan->deskripsi ?? $susulan->tugas->deskripsi,
            'nama_mapel' => $susulan->tugas->mapel->nama_mapel ?? null,
            'guru' => $susulan->tugas->guru->nama ?? null,
            'deadline_susulan' => $susulan->deadline,
            'original_deadline' => $susulan->tugas->deadline,
            'keterangan' => $susulan->keterangan,
            'status' => $status,
            'pengumpulan' => $pengumpulan ? [
                'id' => $pengumpulan->id,
                'file' => $pengumpulan->file,
                'link' => $pengumpulan->link,
                'submitted_at' => $pengumpulan->submitted_at,
                'nilai' => $pengumpulan->nilai?->score ?? 'Belum dinilai'
            ] : null
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
