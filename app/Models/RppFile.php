<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RppFile extends Model
{
    protected $fillable = [
        'rpp_id',
        'nama_file',
        'tipe',
        'url',
    ];

    public function rpp()
    {
        return $this->belongsTo(Rpp::class);
    }
}
