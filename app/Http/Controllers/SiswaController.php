<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiswaController extends Controller
{
    public function index(Request $request)
    {
        $perKelas = $request->query('limit');

        $data = Siswa::select('id', 'nis', 'nama', 'jenis_kelamin', 'jurusan_id')
            ->with([
                'jurusan:id,nama_jurusan',
                'rombel.kelas:id,tingkat'
            ])
            ->get();

        // mapping dulu
        $mapped = $data->map(function ($item) {
            return [
                'id' => $item->id,
                'nis' => $item->nis,
                'nama' => $item->nama,
                'jenis_kelamin' => $item->jenis_kelamin,
                'jurusan' => $item->jurusan->nama_jurusan ?? null,
                'kelas' => $item->rombel->map(fn($r) => optional($r->kelas)->tingkat)->filter()->values()
            ];
        });

        // kalau mau 3 per kelas
        if ($perKelas) {
            $grouped = $mapped->groupBy(function ($item) {
                return $item['kelas'][0] ?? 'Tanpa Kelas';
            })->map(function ($items) use ($perKelas) {
                return $items->take($perKelas);
            });

            return response()->json([
                'data' => $grouped
            ]);
        }

        // default: semua data
        return response()->json([
            'data' => $mapped
        ]);
    }

    public function forGuru(Request $request)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'guru') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $guru = $user->guru;
        if (!$guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak ditemukan',
            ], 404);
        }

        // Dapatkan mapel yang guru ajar
        $mapelIds = $guru->mapel()->pluck('mata_pelajaran.id')->toArray();

        if (empty($mapelIds)) {
            return response()->json([
                'data' => [],
                'message' => 'Guru belum mengajar mata pelajaran apapun',
            ]);
        }

        // Dapatkan rombel yang ada mapel tersebut
        $rombelIds = DB::table('rombel_mapel')
            ->whereIn('mata_pelajaran_id', $mapelIds)
            ->pluck('rombel_id')
            ->unique()
            ->toArray();

        if (empty($rombelIds)) {
            return response()->json([
                'data' => [],
                'message' => 'Tidak ada rombel yang mengikuti mata pelajaran ini',
            ]);
        }

        // Dapatkan siswa dari anggota kelas di rombel tersebut
        $siswaIds = DB::table('anggota_kelas')
            ->whereIn('rombel_id', $rombelIds)
            ->pluck('siswa_id')
            ->unique()
            ->toArray();

        $data = Siswa::whereIn('id', $siswaIds)
            ->select('id', 'nis', 'nama', 'jenis_kelamin', 'jurusan_id')
            ->with([
                'jurusan:id,nama_jurusan',
                'rombel.kelas:id,tingkat'
            ])
            ->get();

        // Mapping data
        $mapped = $data->map(function ($item) {
            return [
                'id' => $item->id,
                'nis' => $item->nis,
                'nama' => $item->nama,
                'jenis_kelamin' => $item->jenis_kelamin,
                'jurusan' => $item->jurusan->nama_jurusan ?? null,
                'kelas' => $item->rombel->map(fn($r) => optional($r->kelas)->tingkat)->filter()->values()
            ];
        });

        return response()->json([
            'data' => $mapped,
            'total' => $mapped->count(),
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'data' => Siswa::with('jurusan', 'rombel.kelas')->findOrFail($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $siswa = Siswa::findOrFail($id);

        $payload = $request->validate([
            'nis' => 'sometimes|string|max:255',
            'nama' => 'sometimes|string|max:255',
            'jurusan_id' => 'sometimes|exists:jurusan,id',
            'rombel_id' => 'sometimes|exists:rombel,id',
            'jenis_kelamin' => 'sometimes|in:Laki-laki,Perempuan',
        ]);

        $updateData = collect($payload)->only(['nis', 'nama', 'jurusan_id', 'jenis_kelamin'])->toArray();
        if (!empty($updateData)) {
            $siswa->update($updateData);
        }

        if (array_key_exists('rombel_id', $payload)) {
            $siswa->rombel()->sync([$payload['rombel_id']]);

            // Sinkronkan juga jurusan_id siswa dengan jurusan_id dari rombel baru
            $rombelBaru = \App\Models\Rombel::find($payload['rombel_id']);
            if ($rombelBaru) {
                $siswa->update(['jurusan_id' => $rombelBaru->jurusan_id]);
            }
        }

        return response()->json([
            'message' => 'Data siswa berhasil diupdate',
            'data' => $siswa->load('jurusan:id,nama_jurusan', 'rombel.kelas:id,tingkat'),
        ]);
    }
    public function getProfile()
    {
        $user = auth('api')->user();
        if ($user->role !== 'siswa') {
            return response()->json(['message' => 'Hanya siswa yang dapat melihat profile'], 403);
        }

$siswa = Siswa::where('user_id', $user->id)->with('user:id,username,role')->first();
        if (!$siswa) {
            return response()->json(['message' => 'Data siswa tidak ditemukan'], 404);
        }

        return response()->json([
            'data' => [     
                'id' => $siswa->id,
                'nis' => $siswa->nis,
                'nama' => $siswa->nama,
                'jenis_kelamin' => $siswa->jenis_kelamin,
                'username' => $siswa->user->username,
                'role' => $siswa->user->role,
            ],
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = auth('api')->user();
        if ($user->role !== 'siswa') {
            return response()->json(['message' => 'Hanya siswa yang dapat mengupdate password'], 403);
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
    


    public function mataPelajaran()
    {
        $user = auth('api')->user();

        if ($user->role !== 'siswa') {
            return response()->json(['message' => 'Unauthorized. Hanya untuk siswa.'], 403);
        }

        $siswa = Siswa::where('user_id', $user->id)->first();
        if (!$siswa) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        // Ambil rombel yang diikuti siswa
        $rombelIds = $siswa->rombel()->pluck('rombel.id');

        // Ambil mata pelajaran yang ada di rombel-rombel tersebut beserta guru yang mengajar
        $mapels = \App\Models\MataPelajaran::whereHas('rombel', function ($q) use ($rombelIds) {
            $q->whereIn('rombel.id', $rombelIds);
        })->with([
                    'guru' => function ($q) {
                        $q->select('guru.id', 'nama');
                    },
                    'rombel.kelas',
                    'rombel.jurusan'
                ])->get();

        $data = $mapels->map(function ($m) use ($rombelIds) {
            $rombel = $m->rombel->first(function ($r) use ($rombelIds) {
                return in_array($r->id, $rombelIds->toArray());
            }) ?: $m->rombel->first();
            return [
                'id' => $m->id,
                'nama_mapel' => $m->nama_mapel,
                'kode_mapel' => $m->kode_mapel,
                'deskripsi' => $m->deskripsi,
                'kelas' => trim(($rombel->kelas->tingkat ?? '') . ' ' . ($rombel->jurusan->nama_jurusan ?? '')),
                'guru' => $m->guru->pluck('nama')->implode(', ') ?: 'Belum ada guru',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function detailMataPelajaran($id)
    {
        $user = auth('api')->user();

        if ($user->role !== 'siswa') {
            return response()->json(['message' => 'Unauthorized. Hanya untuk siswa.'], 403);
        }

        $siswa = Siswa::where('user_id', $user->id)->first();
        if (!$siswa) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        // Ambil rombel yang diikuti siswa
        $rombelIds = $siswa->rombel()->pluck('rombel.id')->toArray();

        // Cek apakah mapel ini ada di rombel yang diikuti siswa
        $mapel = \App\Models\MataPelajaran::where('id', $id)
            ->whereHas('rombel', function ($q) use ($rombelIds) {
                $q->whereIn('rombel.id', $rombelIds);
            })
            ->with([
                'guru' => function ($q) {
                    $q->select('guru.id', 'nama');
                },
                'rombel.kelas',
                'rombel.jurusan'
            ])
            ->first();

        // Jika mapel belum terhubung ke rombel tapi ada materi yang bisa diakses siswa,
        // tetap izinkan menampilkan materi.
        if (!$mapel) {
            $materiForMapel = \App\Models\Materi::where('mapel_id', $id)
                ->where(function ($q) use ($rombelIds) {
                    $q->whereNull('rombel_id')
                        ->orWhereIn('rombel_id', $rombelIds);
                })
                ->exists();

            if (!$materiForMapel) {
                return response()->json(['message' => 'Mata pelajaran tidak ditemukan atau Anda tidak memiliki akses ke mata pelajaran ini.'], 404);
            }

            $mapel = \App\Models\MataPelajaran::with([
                'guru' => function ($q) {
                    $q->select('guru.id', 'nama');
                },
                'rombel.kelas',
                'rombel.jurusan'
            ])->find($id);

            if (!$mapel) {
                return response()->json(['message' => 'Mata pelajaran tidak ditemukan.'], 404);
            }
        }

        // Ambil materi untuk mapel ini yang sesuai dengan rombel siswa (atau materi publik untuk semua rombel di mapel ini)
        $materi = \App\Models\Materi::where('mapel_id', $id)
            ->where(function ($q) use ($rombelIds) {
                $q->whereNull('rombel_id')
                    ->orWhereIn('rombel_id', $rombelIds);
            })
            ->with('files')
            ->orderBy('created_at', 'desc')
            ->get();

        $rombel = $mapel->rombel->first(function ($r) use ($rombelIds) {
            return in_array($r->id, $rombelIds);
        }) ?: $mapel->rombel->first();

        // Hitung jumlah tugas yang belum dikerjakan untuk mapel ini
        $tugasIds = \App\Models\Tugas::where('mapel_id', $id)
            ->where(function ($q) use ($rombelIds) {
                $q->whereNull('rombel_id')
                    ->orWhereIn('rombel_id', $rombelIds);
            })->pluck('id')->toArray();

        $tugasSelesai = \App\Models\Pengumpulan::where('siswa_id', $siswa->id)
            ->whereIn('tugas_id', $tugasIds)
            ->distinct('tugas_id')
            ->count('tugas_id');

        $tugasTertunda = max(0, count($tugasIds) - $tugasSelesai);

        $data = [
            'id' => $mapel->id,
            'nama_mapel' => $mapel->nama_mapel,
            'kode_mapel' => $mapel->kode_mapel,
            'deskripsi' => $mapel->deskripsi,
            'kelas' => trim(($rombel->kelas->tingkat ?? '') . ' ' . ($rombel->jurusan->nama_jurusan ?? '')),
            'guru' => $mapel->guru->pluck('nama')->implode(', ') ?: 'Belum ada guru',
            'tugas_tertunda' => $tugasTertunda,
            'materi' => $materi->map(function ($m) {
                return [
                    'id' => $m->id,
                    'judul' => $m->judul,
                    'deskripsi' => $m->deskripsi,
                    'created_at' => $m->created_at,
                    'files' => $m->files->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'tipe' => $file->tipe, // PDF, VIDEO, IMAGE, FILE, YOUTUBE
                            'url' => $file->url,
                            'nama_file' => $file->nama_file,
                        ];
                    }),
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function tugasMataPelajaran($id)
    {
        $user = auth('api')->user();

        if ($user->role !== 'siswa') {
            return response()->json(['message' => 'Unauthorized. Hanya untuk siswa.'], 403);
        }

        $siswa = Siswa::where('user_id', $user->id)->first();
        if (!$siswa) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $rombelIds = $siswa->rombel()->pluck('rombel.id')->toArray();

        $mapel = \App\Models\MataPelajaran::where('id', $id)
            ->whereHas('rombel', function ($q) use ($rombelIds) {
                $q->whereIn('rombel.id', $rombelIds);
            })
            ->first();

        if (!$mapel) {
            return response()->json(['message' => 'Mata pelajaran tidak ditemukan atau Anda tidak memiliki akses ke mata pelajaran ini.'], 404);
        }

        // Ambil tugas untuk mapel ini yang sesuai dengan rombel siswa (atau tugas publik untuk semua rombel di mapel ini)
        $tugas = \App\Models\Tugas::where('mapel_id', $id)
            ->where(function ($q) use ($rombelIds) {
                $q->whereNull('rombel_id')
                    ->orWhereIn('rombel_id', $rombelIds);
            })
            ->orderBy('deadline', 'asc')
            ->get();

        $tugasWithStatus = $tugas->map(function ($t) use ($siswa) {
            $pengumpulan = \App\Models\Pengumpulan::where('tugas_id', $t->id)
                ->where('siswa_id', $siswa->id)
                ->first();

            return [
                'id' => $t->id,
                'judul' => $t->judul,
                'deskripsi' => $t->deskripsi,
                'deadline' => $t->deadline,
                'status_pengumpulan' => $pengumpulan ? 'Sudah dikumpulkan' : 'Belum dikumpulkan',
            ];
        });

        $tugasTertunda = $tugasWithStatus->where('status_pengumpulan', 'Belum dikumpulkan')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'mapel_id' => $mapel->id,
                'nama_mapel' => $mapel->nama_mapel,
                'tugas_tertunda' => $tugasTertunda,
                'tugas' => $tugasWithStatus
            ]
        ]);
    }

    public function detailTugas($tugasId)
    {
        $user = auth('api')->user();

        if ($user->role !== 'siswa') {
            return response()->json(['message' => 'Unauthorized. Hanya untuk siswa.'], 403);
        }

        $siswa = Siswa::where('user_id', $user->id)->first();
        if (!$siswa) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $rombelIds = $siswa->rombel()->pluck('rombel.id')->toArray();

        $tugas = \App\Models\Tugas::with(['mapel.rombel.kelas', 'mapel.rombel.jurusan', 'guru'])->find($tugasId);

        if (!$tugas) {
            return response()->json(['message' => 'Tugas tidak ditemukan.'], 404);
        }

        // Pastikan tugas ini milik mapel yang ada di rombel siswa atau tugasnya khusus rombel siswa
        $isMapelValid = \App\Models\MataPelajaran::where('id', $tugas->mapel_id)
            ->whereHas('rombel', function ($q) use ($rombelIds) {
                $q->whereIn('rombel.id', $rombelIds);
            })->exists();

        if (!$isMapelValid) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        if ($tugas->rombel_id && !in_array($tugas->rombel_id, $rombelIds)) {
            return response()->json(['message' => 'Akses ditolak. Tugas ini untuk kelas lain.'], 403);
        }

        $pengumpulan = \App\Models\Pengumpulan::with('nilai')
            ->where('tugas_id', $tugasId)
            ->where('siswa_id', $siswa->id)
            ->first();

        $rombelTugas = $tugas->mapel->rombel->first(function ($r) use ($rombelIds) {
            return in_array($r->id, $rombelIds);
        }) ?: $tugas->mapel->rombel->first();

        $data = [
            'id' => $tugas->id,
            'judul' => $tugas->judul,
            'deskripsi' => $tugas->deskripsi,
            'deadline' => $tugas->deadline,
            'nama_mapel' => $tugas->mapel->nama_mapel,
            'kelas' => trim(($rombelTugas->kelas->tingkat ?? '') . ' ' . ($rombelTugas->jurusan->nama_jurusan ?? '')),
            'guru' => $tugas->guru->nama ?? 'Unknown',
            'pengumpulan' => $pengumpulan ? [
                'id' => $pengumpulan->id,
                'file' => $pengumpulan->file,
                'link' => $pengumpulan->link,
                'submitted_at' => $pengumpulan->submitted_at,
                'status' => $pengumpulan->submitted_at ? 'Sudah dikumpulkan' : 'Belum dikumpulkan',
                'nilai' => $pengumpulan->nilai?->score ?? 'Belum dinilai'
            ] : null
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function destroy($id)
    {
        $siswa = Siswa::findOrFail($id);

        DB::transaction(function () use ($siswa) {
            $siswa->delete();
        });

        return response()->json([
            'message' => 'Data siswa berhasil dihapus'
        ]);
    }

    public function resetPassword($id)
    {
        $siswa = Siswa::findOrFail($id);

        $user = $siswa->user;
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Akun user untuk siswa ini tidak ditemukan.'
            ], 404);
        }

        $user->update([
            'password' => \Hash::make('12345678')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password siswa berhasil direset ke "12345678".'
        ]);
    }
}