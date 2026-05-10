<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Kelas extends Model
{
    protected $table = 'kelas';
    protected $fillable = [ 'tingkat', 'tahun_ajaran'];

    public function store(Request $request)
    {
        $kelas = self::create($request->only([ 'tingkat', 'tahun_ajaran']));

        return response()->json([
            'message' => 'Kelas berhasil ditambahkan',
            'data' => $kelas
        ], 201);
    }

    // Relasi ke Rombel
    public function rombel() {
        return $this->hasMany(Rombel::class, 'kelas_id');
    }

    // Relasi ke Mata Pelajaran (yang kamu buat sebelumnya)
    public function mataPelajaran() {
        return $this->hasMany(MataPelajaran::class, 'kelas_id');
    }

    public function siswa()
    {
        // One level deep
        return $this->hasManyThrough(Siswa::class, Rombel::class);
    }
}
