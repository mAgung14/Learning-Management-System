<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Siswa;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\Rombel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $rombel;

    protected function setUp(): void
    {
        parent::setUp();

        // Menyiapkan data master untuk pengujian registrasi
        $kelas = Kelas::create(['tingkat' => 'XI', 'tahun_ajaran' => '2025/2026']);
        $jurusan = Jurusan::create(['nama_jurusan' => 'PPLG']);
        $this->rombel = Rombel::create(['kelas_id' => $kelas->id, 'jurusan_id' => $jurusan->id]);

        // Membuat pengguna dengan role Admin
        $this->admin = User::create([
            'username' => 'admin_test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
    }

    public function test_user_bisa_login_dengan_kredensial_yang_benar()
    {
        // Mengirimkan request login dengan data yang benar
        $response = $this->postJson('/api/login', [
            'username' => 'admin_test',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'username', 'role'],
                'token',
                'token_type',
                'expires_in',
            ]
        ]);
    }

    public function test_user_tidak_bisa_login_dengan_kredensial_yang_salah()
    {
        // Mengirimkan request login dengan password salah
        $response = $this->postJson('/api/login', [
            'username' => 'admin_test',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Username atau password salah');
    }

    public function test_user_terautentikasi_bisa_mengambil_profil_me()
    {
        // Menghasilkan token JWT untuk admin
        $token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($this->admin);

        // Mengakses detail profil sendiri dengan menyertakan token di header
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.username', 'admin_test');
    }

    public function test_user_tidak_terautentikasi_tidak_bisa_mengakses_me()
    {
        // Mencoba mengakses profil me tanpa autentikasi (tanpa token)
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_user_bisa_memperbarui_token()
    {
        // Menghasilkan token JWT awal
        $token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($this->admin);

        // Memperbarui token JWT lama menjadi token baru
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/refresh');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'token',
                'token_type',
                'expires_in',
            ]
        ]);
    }

    public function test_user_bisa_logout()
    {
        // Menghasilkan token JWT
        $token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($this->admin);

        // Melakukan proses logout (membuat token menjadi tidak aktif)
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Logout berhasil');
    }

    public function test_admin_bisa_mengakses_data_form_registrasi()
    {
        // Admin mengakses form data registrasi (mengambil rombel, jurusan, kelas)
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/register-form');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => ['rombel', 'jurusan', 'kelas']
        ]);
    }

    public function test_non_admin_tidak_bisa_mengakses_data_form_registrasi()
    {
        // Membuat user siswa biasa
        $studentUser = User::create([
            'username' => 'siswa_test',
            'password' => bcrypt('password123'),
            'role' => 'siswa',
        ]);

        // Siswa biasa mencoba mengakses form registrasi admin
        $response = $this->actingAs($studentUser, 'api')
            ->getJson('/api/register-form');

        // Harus ditolak dengan status 403 Forbidden
        $response->assertStatus(403);
    }

    public function test_admin_bisa_mendaftarkan_siswa_baru()
    {
        // Admin meregistrasikan siswa baru
        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/register', [
                'username' => 'siswa_baru',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'nama' => 'Siswa Baru Test',
                'jenis_kelamin' => 'Laki-laki',
                'role' => 'siswa',
                'rombel_id' => $this->rombel->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Registrasi berhasil');

        // Memastikan data user dan siswa tersimpan di database
        $this->assertDatabaseHas('users', [
            'username' => 'siswa_baru',
            'role' => 'siswa',
        ]);

        $this->assertDatabaseHas('siswa', [
            'nama' => 'Siswa Baru Test',
            'jenis_kelamin' => 'Laki-laki',
        ]);
    }

    public function test_admin_bisa_mendaftarkan_guru_baru()
    {
        // Admin meregistrasikan guru baru
        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/register', [
                'username' => 'guru_baru',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'nama' => 'Guru Baru Test',
                'jenis_kelamin' => 'Perempuan',
                'role' => 'guru',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        // Memastikan data user dan guru tersimpan di database
        $this->assertDatabaseHas('users', [
            'username' => 'guru_baru',
            'role' => 'guru',
        ]);

        $this->assertDatabaseHas('guru', [
            'nama' => 'Guru Baru Test',
            'jenis_kelamin' => 'Perempuan',
        ]);
    }

    public function test_admin_bisa_reset_password_guru()
    {
        $guruUser = User::create([
            'username' => 'guru_reset_test',
            'password' => bcrypt('oldpassword'),
            'role' => 'guru',
        ]);

        $guru = Guru::create([
            'user_id' => $guruUser->id,
            'nik' => '123456',
            'nama' => 'Guru Reset Test',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/guru/{$guru->id}/reset-password");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Password guru berhasil direset ke "12345678".');

        // Check password changed
        $this->assertTrue(\Hash::check('12345678', $guruUser->fresh()->password));
    }

    public function test_non_admin_tidak_bisa_reset_password_guru()
    {
        $guruUser = User::create([
            'username' => 'guru_reset_test',
            'password' => bcrypt('oldpassword'),
            'role' => 'guru',
        ]);

        $guru = Guru::create([
            'user_id' => $guruUser->id,
            'nik' => '123456',
            'nama' => 'Guru Reset Test',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        $anotherGuru = User::create([
            'username' => 'guru_another',
            'password' => bcrypt('password123'),
            'role' => 'guru',
        ]);

        $response = $this->actingAs($anotherGuru, 'api')
            ->postJson("/api/guru/{$guru->id}/reset-password");

        $response->assertStatus(403);
    }

    public function test_admin_bisa_reset_password_siswa()
    {
        $siswaUser = User::create([
            'username' => 'siswa_reset_test',
            'password' => bcrypt('oldpassword'),
            'role' => 'siswa',
        ]);

        $siswa = Siswa::create([
            'user_id' => $siswaUser->id,
            'nis' => '123456',
            'nama' => 'Siswa Reset Test',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/siswa/{$siswa->id}/reset-password");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Password siswa berhasil direset ke "12345678".');

        // Check password changed
        $this->assertTrue(\Hash::check('12345678', $siswaUser->fresh()->password));
    }
}
