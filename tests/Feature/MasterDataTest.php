<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Models\Guru;
use App\Models\Rombel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $siswaUser;
    private $guruUser;
    private $guru;
    private $rombel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'admin_master',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->siswaUser = User::create([
            'username' => 'siswa_master',
            'password' => bcrypt('password123'),
            'role' => 'siswa',
        ]);

        $this->guruUser = User::create([
            'username' => 'guru_master',
            'password' => bcrypt('password123'),
            'role' => 'guru',
        ]);

        $this->guru = Guru::create([
            'user_id' => $this->guruUser->id,
            'nik' => '99912',
            'nama' => 'Guru Master Test',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        $kelas = Kelas::create(['tingkat' => 'X', 'tahun_ajaran' => '2025/2026']);
        $jurusan = Jurusan::create(['nama_jurusan' => 'PPLG']);
        $this->rombel = Rombel::create(['kelas_id' => $kelas->id, 'jurusan_id' => $jurusan->id]);
    }

    /*
     |--------------------------------------------------------------------------
     | PENGUJIAN KELAS
     |--------------------------------------------------------------------------
     */

    public function test_admin_bisa_melakukan_crud_kelas()
    {
        // 1. Buat Kelas Baru (Create)
        $responseCreate = $this->actingAs($this->admin, 'api')
            ->postJson('/api/kelas', [
                'tingkat' => 'XII',
                'tahun_ajaran' => '2026/2027',
            ]);
        $responseCreate->assertStatus(200);
        $responseCreate->assertJsonPath('message', 'Kelas berhasil dibuat');
        $kelasId = $responseCreate->json('data.id');

        $this->assertDatabaseHas('kelas', [
            'id' => $kelasId,
            'tingkat' => 'XII',
            'tahun_ajaran' => '2026/2027',
        ]);

        // 2. Baca Semua Kelas (Read / Index)
        $responseIndex = $this->actingAs($this->admin, 'api')
            ->getJson('/api/kelas');
        $responseIndex->assertStatus(200);
        $responseIndex->assertJsonCount(2, 'data'); // 1 dari setUp, 1 dari pembuatan di atas

        // 3. Baca Detail Kelas (Read / Show)
        $responseShow = $this->actingAs($this->admin, 'api')
            ->getJson("/api/kelas/{$kelasId}");
        $responseShow->assertStatus(200);
        $responseShow->assertJsonPath('data.tingkat', 'XII');

        // 4. Perbarui Kelas (Update)
        $responseUpdate = $this->actingAs($this->admin, 'api')
            ->putJson("/api/kelas/{$kelasId}", [
                'tingkat' => 'XII-RPL',
                'tahun_ajaran' => '2026/2027',
            ]);
        $responseUpdate->assertStatus(200);
        $responseUpdate->assertJsonPath('message', 'Kelas berhasil diupdate');
        $this->assertDatabaseHas('kelas', [
            'id' => $kelasId,
            'tingkat' => 'XII-RPL',
        ]);

        // 5. Hapus Kelas (Delete)
        $responseDelete = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/kelas/{$kelasId}");
        $responseDelete->assertStatus(200);
        $responseDelete->assertJsonPath('message', 'Kelas berhasil dihapus');
        $this->assertDatabaseMissing('kelas', ['id' => $kelasId]);
    }

    public function test_non_admin_tidak_bisa_membuat_kelas()
    {
        // Memastikan selain admin (misal siswa) dilarang membuat data kelas baru
        $response = $this->actingAs($this->siswaUser, 'api')
            ->postJson('/api/kelas', [
                'tingkat' => 'XII',
                'tahun_ajaran' => '2026/2027',
            ]);

        $response->assertStatus(403);
    }

    /*
     |--------------------------------------------------------------------------
     | PENGUJIAN JURUSAN
     |--------------------------------------------------------------------------
     */

    public function test_admin_bisa_melakukan_crud_jurusan()
    {
        // 1. Buat Jurusan Baru (Create)
        $responseCreate = $this->actingAs($this->admin, 'api')
            ->postJson('/api/jurusan', [
                'nama_jurusan' => 'Animasi',
            ]);
        $responseCreate->assertStatus(201);
        $responseCreate->assertJsonPath('message', 'Jurusan berhasil ditambahkan');
        $jurusanId = $responseCreate->json('data.id');

        $this->assertDatabaseHas('jurusan', [
            'id' => $jurusanId,
            'nama_jurusan' => 'Animasi',
        ]);

        // 2. Baca Detail Jurusan (Read / Show)
        $responseShow = $this->actingAs($this->admin, 'api')
            ->getJson("/api/jurusan/{$jurusanId}");
        $responseShow->assertStatus(200);
        $responseShow->assertJsonPath('data.nama_jurusan', 'Animasi');

        // 3. Perbarui Jurusan (Update)
        $responseUpdate = $this->actingAs($this->admin, 'api')
            ->putJson("/api/jurusan/{$jurusanId}", [
                'nama_jurusan' => 'Multimedia',
            ]);
        $responseUpdate->assertStatus(200);
        $this->assertDatabaseHas('jurusan', [
            'id' => $jurusanId,
            'nama_jurusan' => 'Multimedia',
        ]);

        // 4. Hapus Jurusan (Delete)
        $responseDelete = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/jurusan/{$jurusanId}");
        $responseDelete->assertStatus(200);
        $this->assertDatabaseMissing('jurusan', ['id' => $jurusanId]);
    }

    /*
     |--------------------------------------------------------------------------
     | PENGUJIAN MATA PELAJARAN
     |--------------------------------------------------------------------------
     */

    public function test_admin_bisa_melakukan_crud_mata_pelajaran_beserta_relasinya()
    {
        // 1. Buat Mapel Baru beserta relasi Guru dan Rombel (Create)
        $responseCreate = $this->actingAs($this->admin, 'api')
            ->postJson('/api/mata-pelajaran', [
                'nama_mapel' => 'Fisika',
                'kode_mapel' => 'FIS01',
                'deskripsi' => 'Belajar Fisika Dasar',
                'guru_ids' => [$this->guru->id],
                'rombel_ids' => [$this->rombel->id]
            ]);

        $responseCreate->assertStatus(201);
        $responseCreate->assertJsonPath('message', 'Mata pelajaran berhasil dibuat');
        $mapelId = $responseCreate->json('data.id');

        $this->assertDatabaseHas('mata_pelajaran', [
            'id' => $mapelId,
            'nama_mapel' => 'Fisika',
            'kode_mapel' => 'FIS01',
        ]);

        $this->assertDatabaseHas('guru_mapel', [
            'guru_id' => $this->guru->id,
            'mata_pelajaran_id' => $mapelId,
        ]);

        $this->assertDatabaseHas('rombel_mapel', [
            'rombel_id' => $this->rombel->id,
            'mata_pelajaran_id' => $mapelId,
        ]);

        // 2. Baca Semua Mata Pelajaran (Read / Index)
        $responseIndex = $this->actingAs($this->admin, 'api')
            ->getJson('/api/mata-pelajaran');
        $responseIndex->assertStatus(200);
        $responseIndex->assertJsonStructure(['data']);

        // 3. Mengakses Form Data Pilihan (Form Data endpoint)
        $responseForm = $this->actingAs($this->admin, 'api')
            ->getJson('/api/mata-pelajaran/form-data');
        $responseForm->assertStatus(200);
        $responseForm->assertJsonStructure(['guru', 'rombel']);

        // 4. Perbarui Mata Pelajaran & Relasi (Update)
        $responseUpdate = $this->actingAs($this->admin, 'api')
            ->putJson("/api/mata-pelajaran/{$mapelId}", [
                'nama_mapel' => 'Fisika Terapan',
                'kode_mapel' => 'FIS01-T',
                'guru_ids' => [], // Menghapus guru pengajar
            ]);
        $responseUpdate->assertStatus(200);
        $this->assertDatabaseHas('mata_pelajaran', [
            'id' => $mapelId,
            'nama_mapel' => 'Fisika Terapan',
        ]);
        $this->assertDatabaseMissing('guru_mapel', [
            'guru_id' => $this->guru->id,
            'mata_pelajaran_id' => $mapelId,
        ]);

        // 5. Hapus Mata Pelajaran (Delete)
        $responseDelete = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/mata-pelajaran/{$mapelId}");
        $responseDelete->assertStatus(200);
        $this->assertDatabaseMissing('mata_pelajaran', ['id' => $mapelId]);
    }
}
