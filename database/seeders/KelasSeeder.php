<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Kelas;

class KelasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kelas = [
            ['tingkat' => "X", 'tahun_ajaran' => '2025/2026'],
            ['tingkat' => "XI", 'tahun_ajaran' => '2024/2025'],
            ['tingkat' => "XII", 'tahun_ajaran' => '2023/2024'],
        ];

        foreach ($kelas as $k) {
            Kelas::create($k);
        }
    }
}