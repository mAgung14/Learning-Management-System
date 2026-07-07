<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rpp extends Model
{
    protected $fillable = [
        'guru_id',
        'mapel_id',
        'judul',
        'deskripsi',
    ];

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }

    public function mapel()
    {
        return $this->belongsTo(MataPelajaran::class, 'mapel_id');
    }

    public function files()
    {
        return $this->hasMany(RppFile::class);
    }
}
