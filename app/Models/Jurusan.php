<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jurusan extends Model
{

    protected $table = 'jurusan';

    protected $primaryKey = 'id'; // ubah primary key ke id

    protected $fillable = [
        'nama_jurusan'
    ];

    public function siswa()
    {
        return $this->hasMany(Siswa::class);
    }
}
