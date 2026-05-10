<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnggotaKelas extends Model
{
    protected $table    = 'anggota_kelas';
    protected $fillable = ['siswa_id', 'rombel_id'];

    // ── Relasi ────────────────────────────────────────────────────────────────

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function rombel()
    {
        return $this->belongsTo(Rombel::class, 'rombel_id');
    }
}
