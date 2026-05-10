<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Jurusan;

class JurusanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jurusan = [
            ['nama_jurusan' => 'Akuntansi Keuangan Lembaga'],
            ['nama_jurusan' => 'Manajemen Perkantoran dan Pelayanan Bisnis'],
            ['nama_jurusan' => 'Pemasaran'],
        ];

        foreach ($jurusan as $j) {
            Jurusan::updateOrCreate(
                ['nama_jurusan' => $j['nama_jurusan']],
                $j
            );
        }
    }
}