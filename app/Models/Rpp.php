<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rpp extends Model
{
    protected $fillable = [
        'guru_id',
        'mapel_id',
        'rombel_id',
        'kompetensi_dasar',
        'indikator',
        'tujuan_pembelajaran',
        'status',
    ];

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

    public function files()
    {
        return $this->hasMany(RppFile::class);
    }

    public function pertemuans()
    {
        return $this->hasMany(RppPertemuan::class, 'rpp_id');
    }
}
