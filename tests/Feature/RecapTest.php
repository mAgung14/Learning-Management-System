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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class RecapTest extends TestCase
{
    use RefreshDatabase;

    private $adminUser;
    private $guruUser;
    private $guru;
    private $otherGuruUser;
    private $otherGuru;
    private $siswaUser;
    private $siswa;
    private $mapel;
    private $otherMapel;
    private $rombel;
    private $otherRombel;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Kelas, Jurusan, Rombel
        $kelas = Kelas::create(['tingkat' => 'X', 'tahun_ajaran' => '2025/2026']);
        $jurusan = Jurusan::create(['nama_jurusan' => 'PPLG']);
        $this->rombel = Rombel::create(['kelas_id' => $kelas->id, 'jurusan_id' => $jurusan->id]);

        $kelas2 = Kelas::create(['tingkat' => 'XI', 'tahun_ajaran' => '2025/2026']);
        $this->otherRombel = Rombel::create(['kelas_id' => $kelas2->id, 'jurusan_id' => $jurusan->id]);

        // 2. Setup Admin User
        $this->adminUser = User::create([
            'username' => 'admin_recap',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        // 3. Setup Guru Users
        $this->guruUser = User::create([
            'username' => 'guru_recap',
            'password' => bcrypt('password123'),
            'role' => 'guru',
        ]);
        $this->guru = Guru::create([
            'user_id' => $this->guruUser->id,
            'nik' => '11111',
            'nama' => 'Guru Recap',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        $this->otherGuruUser = User::create([
            'username' => 'other_guru_recap',
            'password' => bcrypt('password123'),
            'role' => 'guru',
        ]);
        $this->otherGuru = Guru::create([
            'user_id' => $this->otherGuruUser->id,
            'nik' => '22222',
            'nama' => 'Other Guru Recap',
            'jenis_kelamin' => 'Perempuan',
        ]);

        // 4. Setup Siswa User
        $this->siswaUser = User::create([
            'username' => 'siswa_recap',
            'password' => bcrypt('password123'),
            'role' => 'siswa',
        ]);
        $this->siswa = Siswa::create([
            'user_id' => $this->siswaUser->id,
            'nis' => '33333',
            'nama' => 'Siswa Recap',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        // Tambahkan Siswa ke Rombel
        \DB::table('anggota_kelas')->insert([
            'rombel_id' => $this->rombel->id,
            'siswa_id' => $this->siswa->id,
        ]);

        // 5. Setup Mata Pelajaran
        $this->mapel = MataPelajaran::create([
            'nama_mapel' => 'Matematika Wajib',
            'kode_mapel' => 'MAT01',
        ]);

        $this->otherMapel = MataPelajaran::create([
            'nama_mapel' => 'Bahasa Indonesia',
            'kode_mapel' => 'IND01',
        ]);

        // 6. Hubungkan Guru-Mapel dan Rombel-Mapel
        // Guru 1 mengajar Mapel 1
        \DB::table('guru_mapel')->insert([
            'guru_id' => $this->guru->id,
            'mata_pelajaran_id' => $this->mapel->id,
        ]);

        // Guru 2 mengajar Mapel 2
        \DB::table('guru_mapel')->insert([
            'guru_id' => $this->otherGuru->id,
            'mata_pelajaran_id' => $this->otherMapel->id,
        ]);

        // Rombel 1 terhubung ke Mapel 1 dan Mapel 2
        \DB::table('rombel_mapel')->insert([
            ['rombel_id' => $this->rombel->id, 'mata_pelajaran_id' => $this->mapel->id],
            ['rombel_id' => $this->rombel->id, 'mata_pelajaran_id' => $this->otherMapel->id],
        ]);

        // 7. Setup Tugas dan Nilai
        $tugas = Tugas::create([
            'judul' => 'Tugas Aljabar',
            'deskripsi' => 'Kerjakan LKS',
            'deadline' => Carbon::now()->addDays(2)->toDateTimeString(),
            'mapel_id' => $this->mapel->id,
            'rombel_id' => $this->rombel->id,
            'guru_id' => $this->guru->id,
        ]);

        $pengumpulan = Pengumpulan::create([
            'tugas_id' => $tugas->id,
            'siswa_id' => $this->siswa->id,
            'link' => 'https://example.com',
            'submitted_at' => now(),
        ]);

        Nilai::create([
            'pengumpulan_id' => $pengumpulan->id,
            'siswa_id' => $this->siswa->id,
            'score' => 85,
        ]);
    }

    public function test_unauthenticated_user_cannot_download_recap()
    {
        $response = $this->getJson('/api/recap/nilai?rombel_id=' . $this->rombel->id);
        $response->assertStatus(401);
    }

    public function test_unauthorized_role_siswa_cannot_download_recap()
    {
        $response = $this->actingAs($this->siswaUser, 'api')
            ->getJson('/api/recap/nilai?rombel_id=' . $this->rombel->id);
        $response->assertStatus(403);
    }

    public function test_admin_can_download_single_mapel_recap()
    {
        $response = $this->actingAs($this->adminUser, 'api')
            ->get('/api/recap/nilai?rombel_id=' . $this->rombel->id . '&mapel_id=' . $this->mapel->id);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('Content-Disposition', 'attachment;filename="Rekap_Nilai_X_PPLG_Matematika_Wajib_' . date('Ymd') . '_' . date('H') . date('i') . date('s') . '.xlsx"'); // filename matches format roughly
    }

    public function test_admin_can_download_all_mapels_multi_sheet_recap()
    {
        $response = $this->actingAs($this->adminUser, 'api')
            ->get('/api/recap/nilai?rombel_id=' . $this->rombel->id);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_guru_can_download_recap_for_their_mapel_and_class()
    {
        $response = $this->actingAs($this->guruUser, 'api')
            ->get('/api/recap/nilai?rombel_id=' . $this->rombel->id . '&mapel_id=' . $this->mapel->id);

        $response->assertStatus(200);
    }

    public function test_guru_cannot_download_recap_for_other_mapel()
    {
        // Guru 1 (teaching mapel 1) trying to download Mapel 2 recap
        $response = $this->actingAs($this->guruUser, 'api')
            ->getJson('/api/recap/nilai?rombel_id=' . $this->rombel->id . '&mapel_id=' . $this->otherMapel->id);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Akses ditolak. Anda tidak mengajar mata pelajaran ini.');
    }

    public function test_guru_cannot_download_recap_for_other_class()
    {
        // Guru 1 trying to download recap for other rombel
        $response = $this->actingAs($this->guruUser, 'api')
            ->getJson('/api/recap/nilai?rombel_id=' . $this->otherRombel->id . '&mapel_id=' . $this->mapel->id);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Akses ditolak. Anda tidak mengajar di kelas ini.');
    }
}
