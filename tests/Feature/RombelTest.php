<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\Rombel;
use App\Models\MataPelajaran;
use App\Models\AnggotaKelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RombelTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $rombelSource;
    private $rombelTarget;
    private $siswa;
    private $mapel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'admin_rombel',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $kelasSource = Kelas::create(['tingkat' => 'X', 'tahun_ajaran' => '2025/2026']);
        $kelasTarget = Kelas::create(['tingkat' => 'XI', 'tahun_ajaran' => '2026/2027']);
        $jurusan = Jurusan::create(['nama_jurusan' => 'PPLG']);

        $this->rombelSource = Rombel::create(['kelas_id' => $kelasSource->id, 'jurusan_id' => $jurusan->id]);
        $this->rombelTarget = Rombel::create(['kelas_id' => $kelasTarget->id, 'jurusan_id' => $jurusan->id]);

        $siswaUser = User::create([
            'username' => 'siswa_rombel',
            'password' => bcrypt('password123'),
            'role' => 'siswa',
        ]);
        $this->siswa = Siswa::create([
            'user_id' => $siswaUser->id,
            'nis' => '88881',
            'nama' => 'Siswa Rombel',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        $this->mapel = MataPelajaran::create([
            'nama_mapel' => 'Bahasa Inggris',
            'kode_mapel' => 'ENG01',
        ]);
    }

    public function test_admin_bisa_melakukan_crud_rombel()
    {
        // 1. Tampilkan Daftar Rombel (Index)
        $responseIndex = $this->actingAs($this->admin, 'api')
            ->getJson('/api/rombel');
        $responseIndex->assertStatus(200);
        $responseIndex->assertJsonCount(2, 'data');

        // 2. Buat Rombel Baru (Create)
        $kelasNew = Kelas::create(['tingkat' => 'XII', 'tahun_ajaran' => '2027/2028']);
        $responseCreate = $this->actingAs($this->admin, 'api')
            ->postJson('/api/rombel', [
                'kelas_id' => $kelasNew->id,
                'jurusan_id' => $this->rombelSource->jurusan_id,
                'tahun_ajaran' => '2027/2028_updated'
            ]);
        $responseCreate->assertStatus(201);
        $rombelId = $responseCreate->json('data.id');

        $this->assertDatabaseHas('rombel', [
            'id' => $rombelId,
            'kelas_id' => $kelasNew->id,
        ]);
        $this->assertEquals('2027/2028_updated', $kelasNew->fresh()->tahun_ajaran);

        // 3. Detail Rombel (Show)
        $responseShow = $this->actingAs($this->admin, 'api')
            ->getJson("/api/rombel/{$rombelId}");
        $responseShow->assertStatus(200);
        $responseShow->assertJsonStructure(['data' => ['id', 'tingkat', 'nama_jurusan', 'siswa']]);

        // 4. Perbarui Rombel (Update)
        $kelasUpdate = Kelas::create(['tingkat' => 'XII-RPL-1', 'tahun_ajaran' => '2028/2029']);
        $responseUpdate = $this->actingAs($this->admin, 'api')
            ->putJson("/api/rombel/{$rombelId}", [
                'kelas_id' => $kelasUpdate->id,
                'tahun_ajaran' => '2028/2029_updated'
            ]);
        $responseUpdate->assertStatus(200);
        $this->assertDatabaseHas('rombel', [
            'id' => $rombelId,
            'kelas_id' => $kelasUpdate->id,
        ]);
        $this->assertEquals('2028/2029_updated', $kelasUpdate->fresh()->tahun_ajaran);

        // 5. Hapus Rombel (Delete)
        $responseDelete = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/rombel/{$rombelId}");
        $responseDelete->assertStatus(200);
        $this->assertDatabaseMissing('rombel', ['id' => $rombelId]);
    }

    public function test_admin_bisa_memasukkan_siswa_ke_rombel()
    {
        // Menetapkan siswa ke dalam rombel kelas
        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/rombel/{$this->rombelSource->id}/assign", [
                'siswa_id' => $this->siswa->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('anggota_kelas', [
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelSource->id,
        ]);

        // Mencegah penetapan siswa yang sama ke rombel lain (Siswa tidak boleh punya 2 rombel aktif)
        $responseDuplicate = $this->actingAs($this->admin, 'api')
            ->postJson("/api/rombel/{$this->rombelTarget->id}/assign", [
                'siswa_id' => $this->siswa->id,
            ]);
        $responseDuplicate->assertStatus(409);
    }

    public function test_admin_bisa_mengeluarkan_siswa_dari_rombel()
    {
        // Setup data anggota kelas
        AnggotaKelas::create([
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelSource->id,
        ]);

        // Mengeluarkan siswa dari rombel
        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/rombel/{$this->rombelSource->id}/kick/{$this->siswa->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('anggota_kelas', [
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelSource->id,
        ]);
    }

    public function test_admin_bisa_menetapkan_dan_mengambil_mata_pelajaran()
    {
        // Menetapkan Mata Pelajaran ke Rombel (Assign)
        $responseAssign = $this->actingAs($this->admin, 'api')
            ->postJson("/api/rombel/{$this->rombelSource->id}/assign-mapel", [
                'mapel_ids' => [$this->mapel->id]
            ]);

        $responseAssign->assertStatus(200);
        $this->assertDatabaseHas('rombel_mapel', [
            'rombel_id' => $this->rombelSource->id,
            'mata_pelajaran_id' => $this->mapel->id,
        ]);

        // Mendapatkan daftar mapel di rombel terkait (Get)
        $responseGet = $this->actingAs($this->admin, 'api')
            ->getJson("/api/rombel/{$this->rombelSource->id}/mapel");

        $responseGet->assertStatus(200);
        $responseGet->assertJsonPath('data.0.id', $this->mapel->id);
    }

    public function test_admin_bisa_mempromosikan_siswa_ke_rombel_lain()
    {
        // Setup siswa di rombel asal
        AnggotaKelas::create([
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelSource->id,
        ]);

        // Menaikkan kelas semua siswa ke rombel tujuan (Promote)
        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/rombel/promote', [
                'source_rombel_id' => $this->rombelSource->id,
                'target_rombel_id' => $this->rombelTarget->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('total_promoted', 1);

        // Memverifikasi perpindahan rombel di database
        $this->assertDatabaseHas('anggota_kelas', [
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelTarget->id,
        ]);
    }

    public function test_admin_bisa_meluluskan_siswa_dengan_menghapus_akun_dan_siswa()
    {
        // Setup siswa di rombel
        AnggotaKelas::create([
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelSource->id,
        ]);

        // Meluluskan dengan menghapus permanen data siswa beserta akun user-nya
        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/rombel/graduate', [
                'rombel_id' => $this->rombelSource->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Memastikan data siswa dan user telah dihapus permanen dari database
        $this->assertDatabaseMissing('siswa', [
            'id' => $this->siswa->id,
        ]);
        $this->assertDatabaseMissing('users', [
            'id' => $this->siswa->user_id,
        ]);
    }
}
