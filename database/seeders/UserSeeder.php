<?php 
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // ADMIN
        User::create([
            'username' => 'admin01',
            'password' => Hash::make('password'),
            'role' => 'admin'
        ]);

        // GURU (pakai NIK)
        User::create([
            'username' => '3275011234567890',
            'password' => Hash::make('password'),
            'role' => 'guru'
        ]);

        // SISWA (pakai NIS)
        User::create([
            'username' => '2025001',
            'password' => Hash::make('password'),
            'role' => 'siswa'
        ]);
    }
}