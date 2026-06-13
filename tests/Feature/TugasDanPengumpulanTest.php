<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Guru;
use App\Models\Siswa;
use App\Models\MataPelajaran;
use App\Models\Rombel;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\Tugas;
use App\Models\Pengumpulan;
use App\Models\Nilai;
use App\Models\Absensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Tests\TestCase;

class TugasDanPengumpulanTest extends TestCase
{
    use RefreshDatabase;

    private $guruUser;
    private $guru;
    private $siswaUser;
    private $siswa;
    private $mapel;
    private $rombel;

    protected function setUp(): void
    {
        parent::setUp();

        $kelas = Kelas::create(['tingkat' => 'X', 'tahun_ajaran' => '2025/2026']);
        $jurusan = Jurusan::create(['nama_jurusan' => 'PPLG']);
        $this->rombel = Rombel::create(['kelas_id' => $kelas->id, 'jurusan_id' => $jurusan->id]);

        // Menyiapkan Akun Guru
        $this->guruUser = User::create([
            'username' => 'guru_tugas',
            'password' => bcrypt('password123'),
            'role' => 'guru',
        ]);
        $this->guru = Guru::create([
            'user_id' => $this->guruUser->id,
            'nik' => '95555',
            'nama' => 'Guru Tugas',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        // Menyiapkan Akun Siswa
        $this->siswaUser = User::create([
            'username' => 'siswa_tugas',
            'password' => bcrypt('password123'),
            'role' => 'siswa',
        ]);
        $this->siswa = Siswa::create([
            'user_id' => $this->siswaUser->id,
            'nis' => '95556',
            'nama' => 'Siswa Tugas',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        // Menambahkan Siswa ke dalam Rombel kelas
        \DB::table('anggota_kelas')->insert([
            'rombel_id' => $this->rombel->id,
            'siswa_id' => $this->siswa->id,
        ]);

        // Menyiapkan Mata Pelajaran
        $this->mapel = MataPelajaran::create([
            'nama_mapel' => 'Bahasa Inggris Peminatan',
            'kode_mapel' => 'ENG02',
        ]);

        // Hubungkan Guru pengampu dengan Mata Pelajaran
        \DB::table('guru_mapel')->insert([
            'guru_id' => $this->guru->id,
            'mata_pelajaran_id' => $this->mapel->id,
        ]);

        // Hubungkan Rombel dengan Mata Pelajaran
        \DB::table('rombel_mapel')->insert([
            'rombel_id' => $this->rombel->id,
            'mata_pelajaran_id' => $this->mapel->id,
        ]);
    }

    public function test_guru_bisa_melakukan_crud_tugas()
    {
        // 1. Buat Tugas Baru (Create)
        $responseCreate = $this->actingAs($this->guruUser, 'api')
            ->postJson('/api/tugas', [
                'judul' => 'Tugas 1 Grammar',
                'deskripsi' => 'Kerjakan halaman 10',
                'deadline' => Carbon::now()->addDays(2)->toDateTimeString(),
                'mapel_id' => $this->mapel->id,
                'rombel_id' => $this->rombel->id,
            ]);

        $responseCreate->assertStatus(201);
        $tugasId = $responseCreate->json('data.id');

        $this->assertDatabaseHas('tugas', [
            'id' => $tugasId,
            'judul' => 'Tugas 1 Grammar',
            'guru_id' => $this->guru->id,
        ]);

        // 2. Baca Semua Daftar Tugas (Read / Index)
        $responseIndex = $this->actingAs($this->guruUser, 'api')
            ->getJson('/api/tugas');
        $responseIndex->assertStatus(200);
        $responseIndex->assertJsonCount(1, 'data');

        // 3. Detail Tugas (Show)
        $responseShow = $this->actingAs($this->guruUser, 'api')
            ->getJson("/api/tugas/{$tugasId}");
        $responseShow->assertStatus(200);
        $responseShow->assertJsonPath('data.judul', 'Tugas 1 Grammar');

        // 4. Perbarui Tugas (Update)
        $responseUpdate = $this->actingAs($this->guruUser, 'api')
            ->putJson("/api/tugas/{$tugasId}", [
                'judul' => 'Tugas 1 Grammar (Updated)',
            ]);
        $responseUpdate->assertStatus(200);
        $this->assertDatabaseHas('tugas', [
            'id' => $tugasId,
            'judul' => 'Tugas 1 Grammar (Updated)',
        ]);

        // 5. Hapus Tugas (Delete)
        $responseDelete = $this->actingAs($this->guruUser, 'api')
            ->deleteJson("/api/tugas/{$tugasId}");
        $responseDelete->assertStatus(200);
        $this->assertDatabaseMissing('tugas', ['id' => $tugasId]);
    }

    public function test_siswa_bisa_mengumpulkan_tugas_dan_membatalkannya()
    {
        Storage::fake('public');

        // 1. Buat Tugas Baru
        $tugas = Tugas::create([
            'judul' => 'Tugas Menulis',
            'deskripsi' => 'Tulis esai',
            'deadline' => Carbon::now()->addDays(2)->toDateTimeString(),
            'mapel_id' => $this->mapel->id,
            'rombel_id' => $this->rombel->id,
            'guru_id' => $this->guru->id,
        ]);

        $file = UploadedFile::fake()->create('essay.docx', 200);

        // 2. Siswa Mengumpulkan Tugas
        $responseSubmit = $this->actingAs($this->siswaUser, 'api')
            ->postJson('/api/pengumpulan', [
                'tugas_id' => $tugas->id,
                'file' => $file,
            ]);

        $responseSubmit->assertStatus(201);
        $responseSubmit->assertJsonPath('message', 'Pengumpulan tugas berhasil disimpan dan absensi tercatat');
        $pengumpulanId = $responseSubmit->json('data.id');

        $this->assertDatabaseHas('pengumpulan', [
            'id' => $pengumpulanId,
            'tugas_id' => $tugas->id,
            'siswa_id' => $this->siswa->id,
        ]);

        // Memastikan sistem mencatat kehadiran (absensi) secara otomatis
        $this->assertDatabaseHas('absensi', [
            'siswa_id' => $this->siswa->id,
            'tugas_id' => $tugas->id,
            'status' => 'hadir',
        ]);

        // 3. Siswa Membatalkan Pengumpulan Tugas
        $responseCancel = $this->actingAs($this->siswaUser, 'api')
            ->deleteJson("/api/siswa/pengumpulan/{$pengumpulanId}");

        $responseCancel->assertStatus(200);
        $responseCancel->assertJsonPath('success', true);

        // Memastikan data pengumpulan & absensi otomatis dihapus kembali
        $this->assertDatabaseMissing('pengumpulan', ['id' => $pengumpulanId]);
        $this->assertDatabaseMissing('absensi', ['pengumpulan_id' => $pengumpulanId]);
    }

    public function test_guru_bisa_melihat_pengumpulan_tugas_dan_memberikan_nilai()
    {
        // 1. Buat Tugas
        $tugas = Tugas::create([
            'judul' => 'Tugas Menulis',
            'deskripsi' => 'Tulis esai',
            'deadline' => Carbon::now()->addDays(2)->toDateTimeString(),
            'mapel_id' => $this->mapel->id,
            'rombel_id' => $this->rombel->id,
            'guru_id' => $this->guru->id,
        ]);

        // 2. Buat Pengumpulan Tugas Siswa
        $pengumpulan = Pengumpulan::create([
            'tugas_id' => $tugas->id,
            'siswa_id' => $this->siswa->id,
            'link' => 'https://github.com/my-submission',
            'submitted_at' => now(),
        ]);

        // 3. Guru Melihat Daftar Pengumpulan Tugas
        $responseView = $this->actingAs($this->guruUser, 'api')
            ->getJson("/api/tugas/{$tugas->id}/pengumpulan");

        $responseView->assertStatus(200);
        $responseView->assertJsonPath('data.tugas.judul', 'Tugas Menulis');
        $responseView->assertJsonPath('data.daftar_pengumpulan.0.siswa_id', $this->siswa->id);

        // 4. Guru Memberikan Nilai pada Pengumpulan Tugas Siswa
        $responseGrade = $this->actingAs($this->guruUser, 'api')
            ->postJson("/api/pengumpulan/{$pengumpulan->id}/nilai", [
                'score' => 90,
            ]);

        $responseGrade->assertStatus(200);
        $responseGrade->assertJsonPath('success', true);
        $responseGrade->assertJsonPath('data.score', 90);

        // Memastikan data nilai masuk ke database
        $this->assertDatabaseHas('nilai', [
            'pengumpulan_id' => $pengumpulan->id,
            'siswa_id' => $this->siswa->id,
            'score' => 90,
        ]);

        $this->assertEquals(90, $pengumpulan->fresh()->nilai?->score);
    }
}
