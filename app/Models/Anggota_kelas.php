<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Anggota_kelas extends Model
{
    protected $table = 'anggota_kelas';
    protected $fillable = ['rombel_id', 'siswa_id'];

    public function rombel()
    {
        return $this->belongsTo(Rombel::class);
    }

    public function siswa() {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}
