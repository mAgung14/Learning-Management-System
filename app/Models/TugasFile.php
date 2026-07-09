<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TugasFile extends Model
{
    protected $fillable = ['tugas_id', 'file_name', 'file_path'];
    protected $appends = ['file_url'];

    public function getFileUrlAttribute()
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    public function tugas()
    {
        return $this->belongsTo(Tugas::class, 'tugas_id');
    }
}
