<?php

namespace App\Http\Controllers;

use App\Models\Rombel;
use App\Models\AnggotaKelas;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use Validator;
use Illuminate\Http\Request;

class RombelController extends Controller
{

    public function formData()
    {
        // Siswa yang sudah punya rombel
        $sudahPunyaRombel = AnggotaKelas::pluck('siswa_id');

        return response()->json([
            'jurusan' => Jurusan::select('id', 'nama_jurusan')->get(),
            'kelas'   => Kelas::select('id', 'tingkat')->get(),
            'siswa'   => Siswa::select('id', 'nama', 'nis')
                            ->whereNotIn('id', $sudahPunyaRombel)
                            ->get(),
        ]);
    }

    /**
     * GET /rombel
     * Daftar semua rombel + info kelas, jurusan, wali guru, jumlah siswa.
     */
    public function index()
    {
        $rombel = Rombel::with([
            'kelas:id,tingkat,tahun_ajaran',
            'jurusan:id,nama_jurusan',
        ])
        ->withCount('anggotaKelas as total_siswa')
        ->get()
        ->map(fn ($r) => [
            'id'          => $r->id,
            'tingkat'     => $r->kelas?->tingkat,
            'nama_jurusan'     => $r->jurusan?->nama_jurusan,
            'tahun_ajaran' => $r->kelas?->tahun_ajaran,
            'total_siswa' => $r->total_siswa,
        ]);

        return response()->json(['data' => $rombel]);
    }

    /**
     * POST /rombel
     * Buat rombel baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'kelas_id'     => 'required|exists:kelas,id',
            'jurusan_id'   => 'required|exists:jurusan,id',
        ]);

        $rombel = Rombel::create($request->only([
            'kelas_id', 'jurusan_id'
        ]));

        // Update tahun_ajaran di tabel kelas terkait
        if ($request->has('tahun_ajaran')) {
            $kelas = Kelas::find($request->kelas_id);
            if ($kelas) {
                $kelas->update(['tahun_ajaran' => $request->tahun_ajaran]);
            }
        }

        return response()->json([
            'message' => 'Rombel berhasil dibuat.',
            'data'    => $rombel->load([
                'kelas:id,tingkat',
                'jurusan:id,nama_jurusan',
            ]),
        ], 201);
    }

    /**
     * GET /rombel/{id}
     * Detail rombel + semua siswa anggota (untuk tombol "Lihat Semua").
     */
    public function show($id)
    {
        $rombel = Rombel::with([
            'kelas:id,tingkat,tahun_ajaran',
            'jurusan:id,nama_jurusan',
            'anggotaKelas.siswa:id,nama,nis,jenis_kelamin',
            'anggotaKelas.siswa.jurusan:id,nama_jurusan',
        ])->findOrFail($id);

        $siswa = $rombel->anggotaKelas->map(fn ($a) => [
            'anggota_kelas_id' => $a->id,
            'siswa_id'         => $a->siswa->id ?? null,
            'nama'             => $a->siswa->nama ?? null,
            'nis'              => $a->siswa->nis ?? null,
            'jenis_kelamin'    => $a->siswa->jenis_kelamin ?? null,
            'jurusan'          => $a->siswa->jurusan?->nama_jurusan,
        ]);

        return response()->json([
            'data' => [
                'id'          => $rombel->id,
                'tingkat'     => $rombel->kelas?->tingkat,
                'tahun_ajaran' => $rombel->kelas?->tahun_ajaran,
                'nama_jurusan'=> $rombel->jurusan?->nama_jurusan,
                'total_siswa' => $siswa->count(),
                'siswa'       => $siswa,
            ],
        ]);
    }

    /**
     * PUT /rombel/{id}
     * Update rombel.
     */
    public function update(Request $request, $id)
    {
        $rombel = Rombel::findOrFail($id);

        $request->validate([
            'kelas_id'     => 'sometimes|exists:kelas,id',
            'jurusan_id'   => 'sometimes|exists:jurusan,id',
            'tahun_ajaran' => 'sometimes|string',
        ]);

        $rombel->update($request->only([
            'nama_rombel', 'kelas_id', 'jurusan_id'
        ]));

        // Update tahun_ajaran di tabel kelas terkait
        if ($request->has('tahun_ajaran') && $rombel->kelas) {
            $rombel->kelas->update(['tahun_ajaran' => $request->tahun_ajaran]);
        }

        return response()->json([
            'message' => 'Rombel berhasil diupdate.',
            'data'    => $rombel->load([
                'kelas:id,tingkat,tahun_ajaran',
                'jurusan:id,nama_jurusan',
            ]),
        ]);
    }

    /**
     * DELETE /rombel/{id}
     * Hapus rombel (siswa otomatis dikeluarkan via cascade DB).
     */
    public function destroy($id)
    {
        $rombel = Rombel::findOrFail($id);
        $rombel->delete();

        return response()->json([
            'message' => "Rombel berhasil dihapus.",
        ]);
    }

    /**
     * POST /rombel/{id}/assign
     * Tambah satu siswa ke rombel ini (tombol "Tambah Siswa" di frontend).
     */
    public function assign(Request $request, $id)
    {
        $rombel = Rombel::findOrFail($id);

        $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
        ]);

        // Satu siswa hanya boleh satu rombel
        if (AnggotaKelas::where('siswa_id', $request->siswa_id)->exists()) {
            return response()->json([
                'message' => 'Siswa sudah terdaftar di sebuah rombel.',
            ], 409);
        }

        $anggota = AnggotaKelas::create([
            'siswa_id'  => $request->siswa_id,
            'rombel_id' => $rombel->id,
        ]);

        return response()->json([
            'message' => 'Siswa berhasil ditambahkan ke rombel.',
            'data'    => $anggota->load('siswa:id,nama,nis'),
        ], 201);
    }

    /**
     * DELETE /rombel/{id}/kick/{siswa_id}
     * Keluarkan siswa tertentu dari rombel.
     */
    public function kick($id, $siswa_id)
    {
        $anggota = AnggotaKelas::where('rombel_id', $id)
            ->where('siswa_id', $siswa_id)
            ->firstOrFail();

        $nama = $anggota->siswa?->nama ?? 'Siswa';
        $anggota->delete();

        return response()->json([
            'message' => "{$nama} berhasil dikeluarkan dari rombel.",
        ]);
    }

    public function assignMapel($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mapel_ids' => 'required|array',
            'mapel_ids.*' => 'exists:mata_pelajaran,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
    }

    $rombel = Rombel::findOrFail($id);

    $rombel->mataPelajaran()->syncWithoutDetaching($request->mapel_ids);

    return response()->json([
        'message' => 'Mapel berhasil di-assign ke rombel'
    ]);
    }
    public function getMapel($id)
    {
        $rombel = Rombel::with(['mataPelajaran.guru:id,nama'])->findOrFail($id);

        $data = $rombel->mataPelajaran->map(function ($mapel) {
            return [
                'id' => $mapel->id,
                'nama_mapel' => $mapel->nama_mapel,
                'kode_mapel' => $mapel->kode_mapel,
                'deskripsi' => $mapel->deskripsi,
                'guru' => $mapel->guru->pluck('nama')->implode(', ') ?: 'Belum ada guru',
            ];
        });

        return response()->json([
            'data' => $data
        ]);
    }

    public function promote(Request $request)
    {
        $request->validate([
            'source_rombel_id' => 'required|exists:rombel,id',
            'target_rombel_id' => 'required|exists:rombel,id',
        ]);

        $sourceRombel = Rombel::findOrFail($request->source_rombel_id);
        $targetRombel = Rombel::findOrFail($request->target_rombel_id);

        // Ambil semua siswa di rombel asal
        $anggotaList = AnggotaKelas::where('rombel_id', $sourceRombel->id)->get();
        $totalSiswa = $anggotaList->count();

        if ($totalSiswa === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada siswa di rombel asal untuk dipromosikan.'
            ], 400);
        }

        \DB::transaction(function () use ($anggotaList, $targetRombel) {
            foreach ($anggotaList as $anggota) {
                // Update rombel_id ke target rombel
                $anggota->update([
                    'rombel_id' => $targetRombel->id
                ]);

                // Sinkronkan jurusan siswa dengan jurusan rombel baru
                if ($anggota->siswa) {
                    $anggota->siswa->update([
                        'jurusan_id' => $targetRombel->jurusan_id
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Berhasil mempromosikan {$totalSiswa} siswa ke rombel tujuan.",
            'total_promoted' => $totalSiswa
        ]);
    }

    public function graduate(Request $request)
    {
        $request->validate([
            'rombel_id' => 'required|exists:rombel,id',
            'action' => 'required|in:delete,detach',
        ]);

        $rombelId = $request->rombel_id;
        $action = $request->action;

        $siswaIds = AnggotaKelas::where('rombel_id', $rombelId)->pluck('siswa_id')->toArray();
        $totalSiswa = count($siswaIds);

        if ($totalSiswa === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada siswa di rombel tersebut untuk diproses kelulusan.'
            ], 400);
        }

        if ($action === 'delete') {
            $siswas = Siswa::whereIn('id', $siswaIds)->get();
            \DB::transaction(function () use ($siswas) {
                foreach ($siswas as $siswa) {
                    if ($siswa->user_id) {
                        \App\Models\User::destroy($siswa->user_id);
                    } else {
                        $siswa->delete();
                    }
                }
            });
        } elseif ($action === 'detach') {
            AnggotaKelas::where('rombel_id', $rombelId)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => $action === 'delete' 
                ? "Berhasil meluluskan & menghapus {$totalSiswa} siswa beserta akunnya."
                : "Berhasil meluluskan (mengeluarkan) {$totalSiswa} siswa dari rombel aktif (menjadi alumni).",
            'total_processed' => $totalSiswa
        ]);
    }
}
