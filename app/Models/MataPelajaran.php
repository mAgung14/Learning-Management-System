<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MataPelajaran extends Model
{
    // 1. Beritahu Laravel nama tabel yang benar (agar tidak otomatis dicari 'mata_pelajarans')
    protected $table = 'mata_pelajaran';

    // 2. Daftarkan kolom mana saja yang boleh diisi secara massal (Mass Assignment)
    protected $fillable = [
        'nama_mapel',
        'kode_mapel',
        'deskripsi',
    ];


    
    public function rombel()
    {
        return $this->belongsToMany(
            Rombel::class,
            'rombel_mapel'
        );
    }



    public function guru()
    {
        return $this->belongsToMany(Guru::class, 'guru_mapel');
    }
}