<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Siswa;
use App\Models\Guru;
use App\Models\Tugas;
use App\Models\MataPelajaran;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\Rombel;
use App\Models\TugasSusulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class TugasSusulanTest extends TestCase
{
    use RefreshDatabase;

    private $siswaUser;
    private $siswa;
    private $guruUser;
    private $guru;
    private $tugas;
    private $rombel;
    private $mapel;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup master data
        $kelas = Kelas::create(['tingkat' => 'X', 'tahun_ajaran' => '2025/2026']);
        $jurusan = Jurusan::create(['nama_jurusan' => 'PPLG']);
        $this->rombel = Rombel::create(['kelas_id' => $kelas->id, 'jurusan_id' => $jurusan->id]);

        // Guru
        $this->guruUser = User::create([
            'username' => 'guru_test',
            'password' => bcrypt('password123'),
            'role' => 'guru',
        ]);
        $this->guru = Guru::create([
            'user_id' => $this->guruUser->id,
            'nik' => '11111',
            'nama' => 'Guru Test',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        // Mapel
        $this->mapel = MataPelajaran::create([
            'nama_mapel' => 'Matematika',
            'kode_mapel' => 'MTK01',
            'deskripsi' => 'Belajar matematika',
        ]);

        // Relasi Rombel Mapel
        \DB::table('rombel_mapel')->insert([
            'rombel_id' => $this->rombel->id,
            'mata_pelajaran_id' => $this->mapel->id,
        ]);

        // Relasi Guru Mapel
        \DB::table('guru_mapel')->insert([
            'guru_id' => $this->guru->id,
            'mata_pelajaran_id' => $this->mapel->id,
        ]);

        // Siswa
        $this->siswaUser = User::create([
            'username' => 'siswa_test',
            'password' => bcrypt('password123'),
            'role' => 'siswa',
        ]);
        $this->siswa = Siswa::create([
            'user_id' => $this->siswaUser->id,
            'nis' => '22222',
            'nama' => 'Siswa Test',
            'jenis_kelamin' => 'Laki-laki',
            'jurusan_id' => $jurusan->id,
        ]);

        // Anggota Kelas
        \DB::table('anggota_kelas')->insert([
            'rombel_id' => $this->rombel->id,
            'siswa_id' => $this->siswa->id,
        ]);

        // Tugas dengan deadline 1 hari yang lalu (sudah lewat)
        $this->tugas = Tugas::create([
            'judul' => 'Tugas Matematika 1',
            'deskripsi' => 'Kerjakan soal 1-5',
            'deadline' => Carbon::now()->subDay(),
            'mapel_id' => $this->mapel->id,
            'rombel_id' => $this->rombel->id,
            'guru_id' => $this->guru->id,
        ]);
    }

    public function test_siswa_cannot_submit_after_deadline_without_tugas_susulan()
    {
        $response = $this->actingAs($this->siswaUser, 'api')
            ->postJson('/api/pengumpulan', [
                'tugas_id' => $this->tugas->id,
                'link' => 'https://github.com/my-submission',
            ]);

        $response->assertStatus(400);
        $response->assertJsonFragment([
            'message' => 'Batas waktu pengumpulan tugas sudah berakhir pada ' . Carbon::parse($this->tugas->deadline)->format('d M Y, H:i')
        ]);
    }

    public function test_guru_can_create_tugas_susulan_for_siswa()
    {
        $futureDeadline = Carbon::now()->addDay()->toDateTimeString();

        $response = $this->actingAs($this->guruUser, 'api')
            ->postJson('/api/tugas-susulan', [
                'tugas_id' => $this->tugas->id,
                'siswa_id' => $this->siswa->id,
                'deadline' => $futureDeadline,
                'keterangan' => 'Remedial/Susulan khusus',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.siswa_id', $this->siswa->id);

        $this->assertDatabaseHas('tugas_susulan', [
            'tugas_id' => $this->tugas->id,
            'siswa_id' => $this->siswa->id,
            'deadline' => $futureDeadline,
            'keterangan' => 'Remedial/Susulan khusus',
        ]);
    }

    public function test_siswa_can_submit_after_deadline_with_tugas_susulan()
    {
        // 1. Berikan tugas susulan
        $futureDeadline = Carbon::now()->addDay();
        TugasSusulan::create([
            'tugas_id' => $this->tugas->id,
            'siswa_id' => $this->siswa->id,
            'deadline' => $futureDeadline,
            'keterangan' => 'Susulan',
        ]);

        // 2. Kirim pengumpulan tugas
        $response = $this->actingAs($this->siswaUser, 'api')
            ->postJson('/api/pengumpulan', [
                'tugas_id' => $this->tugas->id,
                'link' => 'https://github.com/my-submission',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Pengumpulan tugas berhasil disimpan dan absensi tercatat');

        $this->assertDatabaseHas('pengumpulan', [
            'tugas_id' => $this->tugas->id,
            'siswa_id' => $this->siswa->id,
            'link' => 'https://github.com/my-submission',
        ]);
    }

    public function test_siswa_sees_original_deadline_in_standard_task_endpoint()
    {
        // 1. Berikan tugas susulan
        $futureDeadline = Carbon::now()->addDay()->toDateTimeString();
        TugasSusulan::create([
            'tugas_id' => $this->tugas->id,
            'siswa_id' => $this->siswa->id,
            'deadline' => $futureDeadline,
            'keterangan' => 'Susulan khusus',
        ]);

        // 2. Akses detail tugas sebagai siswa
        $response = $this->actingAs($this->siswaUser, 'api')
            ->getJson("/api/siswa/tugas/{$this->tugas->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.deadline', $this->tugas->deadline->toDateTimeString());
        $response->assertJsonMissingPath('data.tugas_susulan');
    }

    public function test_guru_can_view_tugas_susulan_submissions()
    {
        // 1. Berikan tugas susulan
        $futureDeadline = Carbon::now()->addDay();
        TugasSusulan::create([
            'tugas_id' => $this->tugas->id,
            'siswa_id' => $this->siswa->id,
            'deadline' => $futureDeadline,
            'keterangan' => 'Susulan',
        ]);

        // 2. Akses tugas susulan sebagai guru
        $response = $this->actingAs($this->guruUser, 'api')
            ->getJson("/api/tugas-susulan?tugas_id={$this->tugas->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.siswa_id', $this->siswa->id);
        $response->assertJsonPath('data.0.keterangan', 'Susulan');
    }

    public function test_siswa_can_view_their_own_tugas_susulan_list_and_details()
    {
        $futureDeadline = Carbon::now()->addDay()->toDateTimeString();
        $susulan = TugasSusulan::create([
            'tugas_id' => $this->tugas->id,
            'siswa_id' => $this->siswa->id,
            'deadline' => $futureDeadline,
            'keterangan' => 'Susulan khusus list',
        ]);

        // 1. Cek index list
        $responseList = $this->actingAs($this->siswaUser, 'api')
            ->getJson('/api/siswa/tugas-susulan');

        $responseList->assertStatus(200);
        $responseList->assertJsonCount(1, 'data');
        $responseList->assertJsonPath('data.0.judul_tugas', 'Susulan: ' . $this->tugas->judul);
        $responseList->assertJsonPath('data.0.status', 'Belum dikumpulkan');

        // 2. Cek detail show
        $responseDetail = $this->actingAs($this->siswaUser, 'api')
            ->getJson("/api/siswa/tugas-susulan/{$susulan->id}");

        $responseDetail->assertStatus(200);
        $responseDetail->assertJsonPath('data.judul_tugas', 'Susulan: ' . $this->tugas->judul);
        $responseDetail->assertJsonPath('data.keterangan', 'Susulan khusus list');
    }

    public function test_guru_can_create_tugas_susulan_with_custom_title_and_description()
    {
        $futureDeadline = Carbon::now()->addDay()->toDateTimeString();

        $response = $this->actingAs($this->guruUser, 'api')
            ->postJson('/api/tugas-susulan', [
                'tugas_id' => $this->tugas->id,
                'siswa_id' => $this->siswa->id,
                'deadline' => $futureDeadline,
                'judul' => 'Tugas Remedial Aljabar',
                'deskripsi' => 'Kerjakan LKS halaman 20 nomor 1-5.',
                'keterangan' => 'Remedial khusus',
            ]);

        $response->assertStatus(201);

        // Verify that student sees the custom title and description
        $responseSiswa = $this->actingAs($this->siswaUser, 'api')
            ->getJson("/api/siswa/tugas-susulan/{$response->json('data.id')}");

        $responseSiswa->assertStatus(200);
        $responseSiswa->assertJsonPath('data.judul_tugas', 'Tugas Remedial Aljabar');
        $responseSiswa->assertJsonPath('data.deskripsi_tugas', 'Kerjakan LKS halaman 20 nomor 1-5.');
    }
}
