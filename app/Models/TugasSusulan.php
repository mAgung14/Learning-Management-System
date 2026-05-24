<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TugasSusulan extends Model
{
    protected $table = 'tugas_susulan';
    protected $fillable = ['tugas_id', 'siswa_id', 'judul', 'deskripsi', 'deadline', 'keterangan'];

    public function tugas()
    {
        return $this->belongsTo(Tugas::class, 'tugas_id');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}
