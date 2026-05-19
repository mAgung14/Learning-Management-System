<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Siswa;
use App\Models\Guru;
use App\Models\MataPelajaran;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Materi;
use App\Models\Tugas;
use App\Models\Pengumpulan;
use App\Models\Pengumuman;
use App\Models\Nilai;
use App\Models\Rombel;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    //Dashboard untuk Menampilkan ringkasan informasi penting.
     public function summary()
    {
        $totalSiswa = Siswa::count();
        $totalGuru  = Guru::count();
        $totalMapel = MataPelajaran::count();

        return response()->json([
            'data' => [
                'total_siswa' => $totalSiswa,
                'total_guru'  => $totalGuru,
                'total_mapel' => $totalMapel,
            ]
        ]);
    }

    // Get list jurusan untuk dropdown frontend
    public function getJurusan()
    {
        $jurusan = Jurusan::select('id', 'nama_jurusan')->get();

        return response()->json([
            'data' => $jurusan
        ]);
    }

    // Get list kelas untuk dropdown frontend
    public function getKelas()
    {
        $kelas = Kelas::select('id', 'tingkat', 'tahun_ajaran')->get();

        return response()->json([
            'data' => $kelas
        ]);
    }

    // Dashboard untuk Role Guru
    public function guruDashboard()
    {
        $user = auth('api')->user();

        if ($user->role !== 'guru') {
            return response()->json(['message' => 'Unauthorized. Hanya untuk guru.'], 403);
        }

        $guru = Guru::where('user_id', $user->id)->first();
        if (!$guru) {
            return response()->json(['message' => 'Data guru tidak ditemukan.'], 404);
        }

        $totalMateri = Materi::where('guru_id', $guru->id)->count();
        $totalTugas = Tugas::where('guru_id', $guru->id)->count();

        // Tugas yang belum dinilai: pengumpulan dari tugas milik guru ini yang belum ada nilainya
        $tugasBelumDinilai = Pengumpulan::whereHas('tugas', function ($q) use ($guru) {
            $q->where('guru_id', $guru->id);
        })->doesntHave('nilai')->count();

        // Rombels (Kelas yang diajar oleh guru ini)
        $rombelIds = DB::table('rombel_mapel')
            ->join('guru_mapel', 'rombel_mapel.mata_pelajaran_id', '=', 'guru_mapel.mata_pelajaran_id')
            ->where('guru_mapel.guru_id', $guru->id)
            ->pluck('rombel_mapel.rombel_id')
            ->unique();

        $totalKelas = $rombelIds->count();

        // Ambil preview kelas
        $kelasPreview = Rombel::with(['kelas', 'jurusan'])
            ->whereIn('id', $rombelIds)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'nama_kelas' => ($r->kelas->tingkat ?? '') . ' ' . ($r->jurusan->nama_jurusan ?? ''),
                    'deskripsi' => $r->jurusan->nama_jurusan ?? '',
                ];
            });

        // Mata Pelajaran yang diajar oleh guru ini
        $mapelIds = DB::table('guru_mapel')
            ->where('guru_id', $guru->id)
            ->pluck('mata_pelajaran_id');

        $totalMapel = $mapelIds->count();

        // Ambil preview mata pelajaran
        $mapelPreview = MataPelajaran::with(['rombel.kelas', 'rombel.jurusan'])
            ->whereIn('id', $mapelIds)
            ->get()
            ->map(function ($m) {
                $rombel = $m->rombel->first();
                $tingkat = $rombel->kelas->tingkat ?? '';
                $namaJurusan = $rombel->jurusan->nama_jurusan ?? '';

                return [
                    'id' => $m->id,
                    'nama_mapel' => $m->nama_mapel,
                    'kode_mapel' => $m->kode_mapel,
                    'tingkat' => $tingkat,
                    'jurusan' => $namaJurusan,
                    // Menggabungkan tingkat dan jurusan untuk judul card jika diperlukan frontend
                    'judul_card' => trim($tingkat . ' ' . $namaJurusan),
                    'deskripsi' => $m->deskripsi ?? $m->nama_mapel,
                ];
            });

        return response()->json([
            'data' => [
                'guru_name' => $guru->nama,
                'summary' => [
                    'total_kelas' => $totalKelas,
                    'total_mapel' => $totalMapel,
                    'total_materi' => $totalMateri,
                    'total_tugas' => $totalTugas,
                    'tugas_belum_dinilai' => $tugasBelumDinilai,
                ],
                'kelas_preview' => $kelasPreview,
                'mapel_preview' => $mapelPreview
            ]
        ]);
    }

    public function siswaDashboard()
    {
        $user = auth('api')->user();

        if (!$user || $user->role !== 'siswa') {
            return response()->json(['message' => 'Unauthorized. Hanya untuk siswa.'], 403);
        }

        $siswa = Siswa::where('user_id', $user->id)->first();
        if (!$siswa) {
            return response()->json(['message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $rombelIds = $siswa->rombel()->pluck('rombel.id')->toArray();
        $mapelIds = $rombelIds
            ? DB::table('rombel_mapel')->whereIn('rombel_id', $rombelIds)->pluck('mata_pelajaran_id')->unique()->toArray()
            : [];

        $tugasIds = $rombelIds
            ? Tugas::whereIn('rombel_id', $rombelIds)->pluck('id')->toArray()
            : [];

        $totalMataPelajaran = count($mapelIds);
        $totalTugas = count($tugasIds);

        $tugasSelesai = $tugasIds
            ? Pengumpulan::where('siswa_id', $siswa->id)
                ->whereIn('tugas_id', $tugasIds)
                ->distinct('tugas_id')
                ->count('tugas_id')
            : 0;

        $tugasTertunda = max(0, $totalTugas - $tugasSelesai);

        $kelasPreview = $siswa->rombel()->with(['kelas', 'jurusan'])->get()->map(function ($r) {
            return [
                'id' => $r->id,
                'nama_kelas' => trim(($r->kelas->tingkat ?? '') . ' ' . ($r->jurusan->nama_jurusan ?? '')),
                'deskripsi' => $r->jurusan->nama_jurusan ?? '',
            ];
        })->unique('id')->values();

        $pengumuman = Pengumuman::with(['user:id,username,role', 'mapel:id,nama_mapel', 'anggotaKelas.siswa:id,nis,nama'])
            ->where(function ($query) use ($siswa, $rombelIds) {
                $anggotaKelasIds = $siswa->anggotaKelas()->pluck('id')->toArray();
                $query->whereIn('anggota_kelas_id', $anggotaKelasIds);

                if (!empty($rombelIds)) {
                    $mapelIds = DB::table('rombel_mapel')
                        ->whereIn('rombel_id', $rombelIds)
                        ->pluck('mata_pelajaran_id')
                        ->toArray();

                    if (!empty($mapelIds)) {
                        $query->orWhereIn('mapel_id', $mapelIds);
                    }
                }
            })
            ->latest()
            ->take(5)
            ->get();

        $notifikasiTugas = $tugasTertunda > 0 
            ? "Ada $tugasTertunda tugas yang belum dikerjakan" 
            : "Semua tugas sudah dikerjakan";

        return response()->json([
            'data' => [
                'siswa_name' => $siswa->nama,
                'notifikasi_tugas' => $notifikasiTugas,
                'summary' => [
                    'total_mata_pelajaran' => $totalMataPelajaran,
                    'tugas_tertunda' => $tugasTertunda,
                    'tugas_selesai' => $tugasSelesai,
                ],
                'kelas_preview' => $kelasPreview,
                'pengumuman' => $pengumuman,
            ]
        ]);
    }
}