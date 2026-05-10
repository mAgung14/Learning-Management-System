<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\UserSeeder;
use Database\Seeders\JurusanSeeder;
use Database\Seeders\KelasSeeder;
use Database\Seeders\MataPelajaranSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
    $this->call(JurusanSeeder::class);
    $this->call(KelasSeeder::class);
    $this->call(UserSeeder::class);
    $this->call(MataPelajaranSeeder::class);
    }
    
}
