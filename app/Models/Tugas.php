<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tugas extends Model
{
    protected $table = 'tugas';
    protected $fillable = ['judul', 'deskripsi', 'deadline', 'mapel_id', 'guru_id', 'rombel_id', 'rpp_id'];

    public function files()
    {
        return $this->hasMany(TugasFile::class, 'tugas_id');
    }

    public function rpp()
    {
        return $this->belongsTo(Rpp::class, 'rpp_id');
    }

    public function pengumpulan()
    {
        return $this->hasMany(Pengumpulan::class);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }
    
    public function mapel()
    {
        return $this->belongsTo(MataPelajaran::class, 'mapel_id');
    }

    public function rombel()
    {
        return $this->belongsTo(Rombel::class, 'rombel_id');
    }

    public function tugasSusulan()
    {
        return $this->hasMany(TugasSusulan::class, 'tugas_id');
    }
}
