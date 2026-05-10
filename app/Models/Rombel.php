<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rombel extends Model
{
    protected $table    = 'rombel';
    protected $fillable = ['kelas_id', 'jurusan_id', 'wali_guru_id'];

    // ── Relasi ────────────────────────────────────────────────────────────────

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id');
    }

    public function mataPelajaran()
    {
        return $this->belongsToMany(
            MataPelajaran::class,
            'rombel_mapel'
        );
    }

    /** Anggota via pivot anggota_kelas */
    public function anggotaKelas()
    {
        return $this->hasMany(AnggotaKelas::class, 'rombel_id');
    }

    /** Shortcut: koleksi siswa di rombel ini */
    public function siswa()
    {
        return $this->belongsToMany(Siswa::class, 'anggota_kelas', 'rombel_id', 'siswa_id');
    }
}
