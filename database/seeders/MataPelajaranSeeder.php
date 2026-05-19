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
            ],
            [
                'nama_mapel' => 'Bahasa Indonesia',
                'kode_mapel' => 'K029840',
                'deskripsi' => 'Pelajaran bahasa nasional',
            ],
            [
                'nama_mapel' => 'Bahasa Inggris',
                'kode_mapel' => 'K029841',
                'deskripsi' => 'Belajar bahasa Inggris',
            ],
            [
                'nama_mapel' => 'Kejuruan',
                'kode_mapel' => 'K029842',
                'deskripsi' => 'Pelajaran kejuruan',
            ]
        ]);
    }
}
