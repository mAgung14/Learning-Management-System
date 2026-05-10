<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MataPelajaranSeeder extends Seeder
{
    public function run()
    {
        DB::table('mata_pelajaran')->insert([
            [
                'nama_mapel' => 'Matematika',
                'kode_mapel' => 'K029839',
                'deskripsi' => 'Pelajaran berhitung dan logika',
                'kelas_id' => 1,
                'jurusan_id' => 1,          
            ],
            [
                'nama_mapel' => 'Bahasa Indonesia',
                'kode_mapel' => 'K029840',
                'deskripsi' => 'Pelajaran bahasa nasional',
                'kelas_id' => 1,
                'jurusan_id' => 1
            ],
            [
                'nama_mapel' => 'Bahasa Inggris',
                'kode_mapel' => 'K029841',
                'deskripsi' => 'Belajar bahasa Inggris',
                'kelas_id' => 2,
                'jurusan_id' => 1
            ],
            [
                'nama_mapel' => 'Kejuruan',
                'kode_mapel' => 'K029842',
                'deskripsi' => 'Pelajaran kejuruan',
                'kelas_id' => 2,
                'jurusan_id' => 1
            ]
        ]);
    }
}
