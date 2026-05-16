<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankSoal extends Model
{
    protected $table = 'bank_soal';

    protected $fillable = [
        'tugas_id',
        'materi_id',
        'guru_id',
        'pertanyaan',
        'jawaban',
        'tipe',
        'tingkat_kesulitan',
        'status',
        'urutan',
    ];

    // ─── Relationships ───

    public function tugas()
    {
        return $this->belongsTo(Tugas::class);
    }

    public function materi()
    {
        return $this->belongsTo(Materi::class);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }
}
