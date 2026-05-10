<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use Illuminate\Http\Request;

class AnggotaKelasController extends Controller
{

    public function index(Request $request)
    {
        // ── MODE DETAIL: semua anggota 1 rombel ─────────────────────────────
        if ($request->has('rombel_id')) {
            $anggota = AnggotaKelas::with([
                'siswa.jurusan:id,nama_jurusan',
                'rombel.kelas:id,tingkat',
                'rombel.jurusan:id,nama_jurusan',
            ])
            ->where('rombel_id', $request->rombel_id)
            ->get();

            $rombel = optional($anggota->first()?->rombel);

            return response()->json([
                'rombel_id'  => $request->rombel_id,
                'tingkat'    => $rombel?->kelas?->tingkat,
                'jurusan'    => $rombel?->jurusan?->nama_jurusan,
                'total'      => $anggota->count(),
                'data'       => $anggota->map(fn ($a) => [
                    'anggota_kelas_id' => $a->id,
                    'siswa_id'         => $a->siswa->id,
                    'nama'             => $a->siswa->nama,
                    'nis'              => $a->siswa->nis,
                    'jenis_kelamin'    => $a->siswa->jenis_kelamin,
                    'jurusan'          => $a->siswa->jurusan?->nama_jurusan,
                ]),
            ]);
        }

        // ── MODE RINGKASAN: per rombel, tampil tingkat + jurusan saja ────────
        $semua = AnggotaKelas::with([
            'rombel.kelas:id,tingkat',
            'rombel.jurusan:id,nama_jurusan',
        ])->get()
          ->groupBy('rombel_id');

        $ringkasan = $semua->map(function ($grup, $rombelId) {
            $rombel = $grup->first()->rombel;
            return [
                'rombel_id'    => $rombelId,
                'tingkat'      => $rombel?->kelas?->tingkat,
                'jurusan'      => $rombel?->jurusan?->nama_jurusan,
                'total_siswa'  => $grup->count(),
            ];
        })->values();

        return response()->json(['data' => $ringkasan]);
    }

    /**
     * POST /anggota-kelas
     * Assign siswa ke rombel secara manual.
     */
    public function store(Request $request)
    {
        $request->validate([
            'siswa_id'  => 'required|exists:siswa,id',
            'rombel_id' => 'required|exists:rombel,id',
        ]);

        // Cegah satu siswa masuk lebih dari satu rombel
        if (AnggotaKelas::where('siswa_id', $request->siswa_id)->exists()) {
            return response()->json([
                'message' => 'Siswa sudah terdaftar di sebuah rombel.'
            ], 409);
        }

        $anggota = AnggotaKelas::create([
            'siswa_id'  => $request->siswa_id,
            'rombel_id' => $request->rombel_id,
        ]);

        return response()->json([
            'message' => 'Siswa berhasil ditambahkan ke rombel.',
            'data'    => $anggota->load([
                'siswa:id,nama,nis',
                'rombel:id,nama_rombel',
            ]),
        ], 201);
    }

    /**
     * DELETE /anggota-kelas/{id}
     * Keluarkan siswa dari rombel.
     */
    public function destroy($id)
    {
        $anggota = AnggotaKelas::with('siswa:id,nama')->findOrFail($id);
        $nama    = $anggota->siswa?->nama ?? 'Siswa';
        $anggota->delete();

        return response()->json([
            'message' => "{$nama} berhasil dikeluarkan dari rombel.",
        ]);
    }
}