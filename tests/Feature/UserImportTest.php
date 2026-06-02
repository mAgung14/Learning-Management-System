<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\Guru;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_mengimpor_siswa_lewat_csv()
    {
        // 1. Setup Admin
        $admin = User::create([
            'username' => 'admin_test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        // 2. Setup Rombel
        $kelas = Kelas::create(['tingkat' => 'X', 'tahun_ajaran' => '2025/2026']);
        $jurusan = Jurusan::create(['nama_jurusan' => 'PPLG']);
        $rombel = Rombel::create(['kelas_id' => $kelas->id, 'jurusan_id' => $jurusan->id]);

        // 3. Create CSV Content
        // Column 0: nis, Column 1: nama, Column 2: jenis_kelamin, Column 3: rombel, Column 4: password
        $csvContent = "NIS,Nama,Jenis Kelamin,Rombel,Password\n" .
                      "12345,Siswa Satu,Laki-laki,{$rombel->id},password123\n" .
                      "12346,Siswa Dua,Perempuan,X PPLG,password456\n";

        $file = UploadedFile::fake()->createWithContent('siswa.csv', $csvContent);

        // 4. Send request as Admin
        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/siswa/import', [
                'file' => $file
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        
        // Verify database records
        $this->assertDatabaseHas('users', ['username' => '12345', 'role' => 'siswa']);
        $this->assertDatabaseHas('siswa', ['nis' => '12345', 'nama' => 'Siswa Satu', 'jenis_kelamin' => 'Laki-laki']);
        
        $this->assertDatabaseHas('users', ['username' => '12346', 'role' => 'siswa']);
        $this->assertDatabaseHas('siswa', ['nis' => '12346', 'nama' => 'Siswa Dua', 'jenis_kelamin' => 'Perempuan']);
    }

    public function test_impor_siswa_gagal_jika_ada_kesalahan_validasi()
    {
        $admin = User::create([
            'username' => 'admin_test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        // CSV with duplicate NIS and non-existent Rombel
        $csvContent = "NIS,Nama,Jenis Kelamin,Rombel,Password\n" .
                      "12345,Siswa Satu,Laki-laki,999,password123\n" .
                      "12345,Siswa Dua,Perempuan,999,password456\n";

        $file = UploadedFile::fake()->createWithContent('siswa.csv', $csvContent);

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/siswa/import', [
                'file' => $file
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonStructure(['errors']);
    }

    public function test_admin_bisa_mengimpor_guru_lewat_csv()
    {
        $admin = User::create([
            'username' => 'admin_test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $csvContent = "NIK,Nama,Jenis Kelamin,Password\n" .
                      "98765,Guru Satu,Laki-laki,password123\n" .
                      "98766,Guru Dua,Perempuan,password456\n";

        $file = UploadedFile::fake()->createWithContent('guru.csv', $csvContent);

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/guru/import', [
                'file' => $file
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', ['username' => '98765', 'role' => 'guru']);
        $this->assertDatabaseHas('guru', ['nik' => '98765', 'nama' => 'Guru Satu', 'jenis_kelamin' => 'Laki-laki']);
    }

    public function test_admin_bisa_menghapus_siswa_dan_akun_user_terkait()
    {
        $admin = User::create([
            'username' => 'admin_test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $user = User::create([
            'username' => 'siswa_hapus',
            'password' => bcrypt('password123'),
            'role' => 'siswa',
        ]);

        $siswa = Siswa::create([
            'user_id' => $user->id,
            'nis' => '54321',
            'nama' => 'Siswa Dihapus',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        $response = $this->actingAs($admin, 'api')
            ->deleteJson("/api/siswa/{$siswa->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('siswa', ['id' => $siswa->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_bisa_menghapus_guru_dan_akun_user_terkait()
    {
        $admin = User::create([
            'username' => 'admin_test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $user = User::create([
            'username' => 'guru_hapus',
            'password' => bcrypt('password123'),
            'role' => 'guru',
        ]);

        $guru = Guru::create([
            'user_id' => $user->id,
            'nik' => '54321',
            'nama' => 'Guru Dihapus',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        $response = $this->actingAs($admin, 'api')
            ->deleteJson("/api/guru/{$guru->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('guru', ['id' => $guru->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
