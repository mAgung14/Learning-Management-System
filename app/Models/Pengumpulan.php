<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengumpulan extends Model
{
    protected $table = 'pengumpulan';
    protected $fillable = ['tugas_id', 'siswa_id', 'file', 'catatan', 'nilai', 'feedback', 'submitted_at'];

    public function tugas()
    {
        return $this->belongsTo(Tugas::class);
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function nilai()
    {
        return $this->hasOne(Nilai::class, 'pengumpulan_id');
    }

    public function absensi()
    {
        return $this->hasOne(Absensi::class, 'pengumpulan_id');
    }
}
