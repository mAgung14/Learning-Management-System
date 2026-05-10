<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileMaterial extends Model
{
    protected $table = 'file_material';

    protected $fillable = [
        'materi_id',
        'tipe',
        'url',
        'nama_file'
    ];

    public function materi()
    {
        return $this->belongsTo(Materi::class);
    }
}
