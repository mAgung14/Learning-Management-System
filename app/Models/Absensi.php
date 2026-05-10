<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    protected $table = 'absensi';
    protected $fillable = [
        'siswa_id',
        'tugas_id',
        'pengumpulan_id',
        'status',
        'keterangan',
        'kehadiran_pada',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function tugas()
    {
        return $this->belongsTo(Tugas::class, 'tugas_id');
    }

    public function pengumpulan()
    {
        return $this->belongsTo(Pengumpulan::class, 'pengumpulan_id');
    }
}
