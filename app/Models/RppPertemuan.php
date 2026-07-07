<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RppPertemuan extends Model
{
    protected $table = 'rpp_pertemuans';

    protected $fillable = [
        'rpp_id',
        'pertemuan_ke',
        'topik',
        'kegiatan_pendahuluan',
        'kegiatan_inti',
        'kegiatan_penutup',
        'alokasi_waktu',
    ];

    public function rpp()
    {
        return $this->belongsTo(Rpp::class);
    }
}
