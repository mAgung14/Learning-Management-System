<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGenerateLog extends Model
{
    protected $table = 'ai_generate_logs';

    protected $fillable = [
        'tugas_id',
        'materi_id',
        'guru_id',
        'status',
        'jumlah_soal_diminta',
        'jumlah_soal_generated',
        'error_message',
        'raw_response',
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
