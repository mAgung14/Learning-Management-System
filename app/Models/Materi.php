<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Materi extends Model
{
    protected $table = 'materi';
     protected $fillable = ['judul','deskripsi','mapel_id','guru_id', 'rombel_id'];

    public function mapel()
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class, 'guru_id');
    }

    public function rombel()
    {
        return $this->belongsTo(Rombel::class, 'rombel_id');
    }

    public function files()
    {
        return $this->hasMany(FileMaterial::class);
    }

    public function bankSoal()
    {
        return $this->hasMany(BankSoal::class);
    }
}
