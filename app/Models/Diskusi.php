<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Diskusi extends Model
{
    protected $fillable = [
        'mata_pelajaran_id',
        'user_id',
        'pesan'
    ];

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
