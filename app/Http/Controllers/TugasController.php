<?php

namespace App\Http\Controllers;

use App\Models\Tugas;
use App\Models\MataPelajaran;
use App\Models\User;
use Illuminate\Http\Request;

class TugasController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Tugas::with(['pengumpulan', 'mapel']);

        if ($user && $user->role === 'siswa') {
            $siswa = $user->siswa;
            // Ambil mapel yang ada di rombel siswa tersebut
            $rombelIds = $siswa?->anggotaKelas()->pluck('rombel_id')->toArray() ?? [];
            
            if ($rombelIds) {
                $mapelIds = \DB::table('rombel_mapel')
                    ->whereIn('rombel_id', $rombelIds)
                    ->pluck('mata_pelajaran_id')
                    ->toArray();
                
                $query->whereIn('mapel_id', $mapelIds)
                    ->where(function ($q) use ($rombelIds) {
                        $q->whereNull('rombel_id')
                          ->orWhereIn('rombel_id', $rombelIds);
                    });
            }
        }

        if ($user && $user->role === 'guru') {
            $guru = $user->guru;
            if ($guru) {
                $mapelIds = $guru->mapel()->pluck('mata_pelajaran.id')->toArray();
                $query->whereIn('mapel_id', $mapelIds);
            }
        }

        $tugasList = $query->latest()->get()->map(function ($tugas) use ($user) {
            $status = 'Belum Mengumpulkan';
            
            if ($user && $user->role === 'siswa') {
                $siswaId = $user->siswa?->id;
                $isCollected = $tugas->pengumpulan()->where('siswa_id', $siswaId)->exists();
                
                if ($isCollected) {
                    $status = 'Sudah Mengumpulkan';
                } else {
                    // Jika belum mengumpulkan, cek apakah sudah lewat deadline
                    if (\Carbon\Carbon::parse($tugas->deadline)->isPast()) {
                        $status = 'Terlambat';
                    }
                }
            }

            // Tambahkan property status ke object tugas
            $tugas->status_pengerjaan = $status;
            return $tugas;
        });

        return response()->json([
            'success' => true,
            'data' => $tugasList
        ]);
    }

    public function store(Request $request)
    {
        try {
            $payload = $request->validate([
                'judul' => 'required|string|max:255',
                'deskripsi' => 'required|string',
                'deadline' => 'required|date',
                'mapel_id' => 'sometimes|exists:mata_pelajaran,id',
                'rombel_id' => 'sometimes|nullable|exists:rombel,id',
            ]);

            // 🔥 ambil user login
            $user = auth()->user();

            if (!$user || $user->role !== 'guru') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Hanya guru yang bisa membuat tugas'
                ], 403);
            }

            // 🔥 ambil guru dari relasi
            $guru = $user->guru;
            if (!$guru) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data guru tidak ditemukan'
                ], 404);
            }

            // 🔥 ambil daftar mapel yang diajarkan guru ini (via guru_mapel)
            $guruMapelIds = $guru->mapel()->pluck('mata_pelajaran.id')->toArray();

            if (empty($guruMapelIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guru ini belum memiliki mata pelajaran yang ditugaskan'
                ], 422);
            }

            // 🔥 tentukan mapel_id
            if (isset($payload['mapel_id'])) {
                // Validasi: mapel yang dipilih harus milik guru ini
                if (!in_array($payload['mapel_id'], $guruMapelIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Mata pelajaran yang dipilih bukan milik guru ini'
                    ], 403);
                }
                $mapel_id = $payload['mapel_id'];
            } else {
                // Jika tidak dikirim, otomatis ambil mapel pertama guru
                $mapel_id = $guruMapelIds[0];
            }

            // 🔥 tentukan rombel_id otomatis dari rombel_mapel (jika tidak dikirim)
            if (isset($payload['rombel_id'])) {
                $rombel_id = $payload['rombel_id'];
            } else {
                // Ambil rombel pertama yang terhubung dengan mapel ini via rombel_mapel
                $mapel = MataPelajaran::find($mapel_id);
                $rombel_id = $mapel ? $mapel->rombel()->pluck('rombel.id')->first() : null;
            }

            // 🔥 create tugas
            $tugas = Tugas::create([
                'judul' => $payload['judul'],
                'deskripsi' => $payload['deskripsi'],
                'deadline' => $payload['deadline'],
                'mapel_id' => $mapel_id,
                'rombel_id' => $rombel_id,
                'guru_id' => $guru->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tugas berhasil dibuat',
                'data' => $tugas->load(['guru']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        return response()->json([
            'data' => Tugas::with(['pengumpulan'])->findOrFail($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $tugas = Tugas::findOrFail($id);
        $user = auth()->user();

        // Otorisasi: Pastikan guru yang mengupdate adalah pembuat tugas ini
        if ($user && $user->role === 'guru') {
            if ($tugas->guru_id !== $user->guru->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Anda bukan pembuat tugas ini.'
                ], 403);
            }
        }

        $payload = $request->validate([
            'judul' => 'sometimes|string|max:255',
            'deskripsi' => 'sometimes|string',
            'deadline' => 'sometimes|date',
            'mapel_id' => 'sometimes|exists:mata_pelajaran,id',
            'mapelId' => 'sometimes|exists:mata_pelajaran,id',
            'guru_id' => 'sometimes|exists:users,id',
            'guruId' => 'sometimes|exists:users,id',
        ]);

        $data = [];
        if (isset($payload['judul'])) {
            $data['judul'] = $payload['judul'];
        }
        if (isset($payload['deskripsi'])) {
            $data['deskripsi'] = $payload['deskripsi'];
        }
        if (isset($payload['deadline'])) {
            $data['deadline'] = $payload['deadline'];
        }
        if (isset($payload['mapel_id'])) {
            $data['mapel_id'] = $payload['mapel_id'];
        }
        if (isset($payload['mapelId'])) {
            $data['mapel_id'] = $payload['mapelId'];
        }
        if (isset($payload['guru_id'])) {
            $data['guru_id'] = $payload['guru_id'];
        }
        if (isset($payload['guruId'])) {
            $data['guru_id'] = $payload['guruId'];
        }

        if (isset($data['guru_id'])) {
            $teacher = User::find($data['guru_id']);
            if (!$teacher || $teacher->role !== 'guru') {
                return response()->json([
                    'message' => 'Guru tidak valid atau bukan user dengan role guru'
                ], 422);
            }
        }

        $tugas->update($data);

        return response()->json([
            'message' => 'Tugas berhasil diupdate',
            'data' => $tugas,
        ]);
    }

    public function destroy($id)
    {
        $tugas = Tugas::findOrFail($id);
        $user = auth()->user();

        // Otorisasi: Pastikan guru yang menghapus adalah pembuat tugas ini
        if ($user && $user->role === 'guru') {
            if ($tugas->guru_id !== $user->guru->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Anda bukan pembuat tugas ini.'
                ], 403);
            }
        }

        $tugas->delete();

        return response()->json([
            'message' => 'Tugas berhasil dihapus'
        ]);
    }

    /**
     * Form data: daftar mapel & rombel milik guru yang login
     * Endpoint: GET /api/guru/tugas/form-data
     */
    public function formData()
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'guru') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $guru = $user->guru;
        if (!$guru) {
            return response()->json(['message' => 'Data guru tidak ditemukan'], 404);
        }

        // Ambil mapel guru beserta rombel yang terkait via rombel_mapel
        $mapels = $guru->mapel()->with(['rombel' => function ($q) {
            $q->with(['kelas:id,tingkat', 'jurusan:id,nama_jurusan']);
        }])->get();

        $data = $mapels->map(function ($mapel) {
            return [
                'id' => $mapel->id,
                'nama_mapel' => $mapel->nama_mapel,
                'rombels' => $mapel->rombel->map(function ($rombel) {
                    return [
                        'id' => $rombel->id,
                        'nama_rombel' => trim(($rombel->kelas->tingkat ?? '') . ' ' . ($rombel->jurusan->nama_jurusan ?? '')),
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function pengumpulanByTugas($id)
    {
        $user = auth('api')->user();

        if (!$user || $user->role !== 'guru') {
            return response()->json(['message' => 'Unauthorized. Hanya guru yang bisa melihat daftar pengumpulan.'], 403);
        }

        $tugas = Tugas::with(['mapel', 'rombel'])->find($id);

        if (!$tugas) {
            return response()->json(['message' => 'Tugas tidak ditemukan'], 404);
        }

        // Verifikasi kepemilikan tugas
        if ($tugas->guru_id !== $user->guru->id) {
            return response()->json(['message' => 'Akses ditolak. Anda bukan pembuat tugas ini.'], 403);
        }

        // Tentukan Rombel mana saja yang mendapat tugas ini
        $rombelIds = [];
        if ($tugas->rombel_id) {
            $rombelIds = [$tugas->rombel_id];
        } else {
            $rombelIds = \Illuminate\Support\Facades\DB::table('rombel_mapel')
                ->where('mata_pelajaran_id', $tugas->mapel_id)
                ->pluck('rombel_id')
                ->toArray();
        }

        // Ambil ID semua siswa di rombel tersebut
        $siswaIds = \Illuminate\Support\Facades\DB::table('anggota_kelas')
            ->whereIn('rombel_id', $rombelIds)
            ->pluck('siswa_id')
            ->unique()
            ->toArray();

        // Ambil data siswa
        $siswas = \App\Models\Siswa::whereIn('id', $siswaIds)->get();

        // Ambil data pengumpulan untuk tugas ini
        $pengumpulans = \App\Models\Pengumpulan::with('nilai')
            ->where('tugas_id', $id)
            ->get();

        $deadline = \Carbon\Carbon::parse($tugas->deadline);
        $totalSiswa = count($siswas);
        $mengumpulkan = $pengumpulans->count();

        $daftarPengumpulan = $siswas->map(function ($siswa) use ($pengumpulans, $deadline) {
            $pengumpulan = $pengumpulans->firstWhere('siswa_id', $siswa->id);
            $isSubmitted = $pengumpulan !== null;
            
            $statusWaktu = '-';
            $waktu = '-';
            $isLate = false;
            
            if ($isSubmitted && $pengumpulan->submitted_at) {
                $submittedAt = \Carbon\Carbon::parse($pengumpulan->submitted_at);
                $waktu = $submittedAt->translatedFormat('d M, H:i');
                $isLate = $submittedAt->gt($deadline);
                $statusWaktu = $isLate ? 'TERLAMBAT' : 'TEPAT WAKTU';
            }

            return [
                'siswa_id' => $siswa->id,
                'nama_siswa' => $siswa->nama,
                'nisn' => $siswa->nis, // Menggunakan NIS sebagai NISN sementara jika field nisn tidak ada
                'waktu' => $waktu,
                'status_waktu' => $statusWaktu,
                'lampiran' => $pengumpulan ? $pengumpulan->file : null,
                'link' => $pengumpulan ? $pengumpulan->link : null,
                'nama_file' => $pengumpulan && $pengumpulan->file ? basename($pengumpulan->file) : 'Belum ada file',
                'status' => $isSubmitted ? 'HADIR' : 'ALFA',
                'nilai' => ($pengumpulan && $pengumpulan->nilai !== null) ? $pengumpulan->nilai : '--',
                'pengumpulan_id' => $pengumpulan ? $pengumpulan->id : null,
            ];
        });

        // Urutkan: yang mengumpulkan (HADIR) di atas, lalu berdasarkan nama
        $daftarPengumpulan = $daftarPengumpulan->sortByDesc('status')->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'tugas' => [
                    'id' => $tugas->id,
                    'judul' => $tugas->judul,
                    'deadline' => $deadline->translatedFormat('d M Y, H:i'),
                ],
                'summary' => [
                    'total_siswa' => $totalSiswa,
                    'mengumpulkan' => $mengumpulkan,
                ],
                'daftar_pengumpulan' => $daftarPengumpulan
            ]
        ]);
    }

    public function berikanNilai(Request $request, $pengumpulanId)
    {
        $user = auth('api')->user();

        if (!$user || $user->role !== 'guru') {
            return response()->json(['message' => 'Unauthorized. Hanya guru yang dapat memberikan nilai.'], 403);
        }

        $payload = $request->validate([
            'score' => 'sometimes|numeric|min:0|max:100',
            'nilai' => 'sometimes|numeric|min:0|max:100',
        ]);

        $scoreValue = $payload['score'] ?? $payload['nilai'] ?? null;

        if ($scoreValue === null) {
            return response()->json(['message' => 'Nilai wajib diisi.'], 422);
        }

        $pengumpulan = \App\Models\Pengumpulan::with('tugas')->find($pengumpulanId);

        if (!$pengumpulan) {
            return response()->json(['message' => 'Data pengumpulan tugas tidak ditemukan.'], 404);
        }

        // Pastikan guru yang memberikan nilai adalah pembuat tugas tersebut
        if ($pengumpulan->tugas->guru_id !== $user->guru->id) {
            return response()->json(['message' => 'Akses ditolak. Anda tidak berhak menilai tugas ini.'], 403);
        }

        $nilai = \App\Models\Nilai::updateOrCreate(
            ['pengumpulan_id' => $pengumpulan->id],
            [
                'siswa_id' => $pengumpulan->siswa_id,
                'score' => $scoreValue,
            ]
        );

        // Update skor ke kolom nilai di tabel pengumpulan sebagai cadangan jika perlu
        $pengumpulan->update(['nilai' => $scoreValue]);

        return response()->json([
            'success' => true,
            'message' => 'Nilai berhasil disimpan',
            'data' => $nilai
        ]);
    }
}
