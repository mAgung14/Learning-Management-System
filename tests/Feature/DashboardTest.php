<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Guru;
use App\Models\Siswa;
use App\Models\MataPelajaran;
use App\Models\Rombel;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\Materi;
use App\Models\Tugas;
use App\Models\Pengumpulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $guruUser;
    private $guru;
    private $siswaUser;
    private $siswa;
    private $rombel;
    private $mapel;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Akun Admin
        $this->admin = User::create([
            'username' => 'admin_dashboard',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        // 2. Setup Master Data
        $kelas = Kelas::create(['tingkat' => 'X', 'tahun_ajaran' => '2025/2026']);
        $jurusan = Jurusan::create(['nama_jurusan' => 'PPLG']);
        $this->rombel = Rombel::create(['kelas_id' => $kelas->id, 'jurusan_id' => $jurusan->id]);

        $this->mapel = MataPelajaran::create([
            'nama_mapel' => 'Kimia',
            'kode_mapel' => 'KIM01',
        ]);

        // 3. Setup Akun Guru
        $this->guruUser = User::create([
            'username' => 'guru_dashboard',
            'password' => bcrypt('password123'),
            'role' => 'guru',
        ]);
        $this->guru = Guru::create([
            'user_id' => $this->guruUser->id,
            'nik' => '93333',
            'nama' => 'Guru Dashboard',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        // 4. Setup Akun Siswa
        $this->siswaUser = User::create([
            'username' => 'siswa_dashboard',
            'password' => bcrypt('password123'),
            'role' => 'siswa',
        ]);
        $this->siswa = Siswa::create([
            'user_id' => $this->siswaUser->id,
            'nis' => '93334',
            'nama' => 'Siswa Dashboard',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        // Masukkan Siswa ke dalam Rombel
        \DB::table('anggota_kelas')->insert([
            'rombel_id' => $this->rombel->id,
            'siswa_id' => $this->siswa->id,
        ]);

        // Hubungkan Guru dengan Mata Pelajaran
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

    public function test_admin_bisa_mengakses_summary_dan_pilihan_dropdown_master()
    {
        // 1. Mengakses Ringkasan Dashboard Admin (Summary)
        $responseSummary = $this->actingAs($this->admin, 'api')
            ->getJson('/api/dashboard/summary');

        $responseSummary->assertStatus(200);
        $responseSummary->assertJsonPath('data.total_siswa', 1);
        $responseSummary->assertJsonPath('data.total_guru', 1);
        $responseSummary->assertJsonPath('data.total_mapel', 1);

        // 2. Mengambil Pilihan Jurusan (Dropdown Jurusan)
        $responseJurusan = $this->actingAs($this->admin, 'api')
            ->getJson('/api/jurusan');
        $responseJurusan->assertStatus(200);
        $responseJurusan->assertJsonCount(1, 'data');

        // 3. Mengambil Pilihan Kelas (Dropdown Kelas)
        $responseKelas = $this->actingAs($this->admin, 'api')
            ->getJson('/api/kelas');
        $responseKelas->assertStatus(200);
        $responseKelas->assertJsonCount(1, 'data');
    }

    public function test_guru_bisa_mengakses_dashboard_guru_dengan_ringkasan_yang_sesuai()
    {
        // Membuat data Materi dan Tugas di bawah Guru ini
        Materi::create([
            'judul' => 'Materi Kimia 1',
            'deskripsi' => 'Pengenalan unsur',
            'mapel_id' => $this->mapel->id,
            'guru_id' => $this->guru->id,
        ]);

        $tugas = Tugas::create([
            'judul' => 'Tugas Unsur',
            'deskripsi' => 'Kerjakan tabel periodik',
            'deadline' => now()->addDays(2),
            'mapel_id' => $this->mapel->id,
            'rombel_id' => $this->rombel->id,
            'guru_id' => $this->guru->id,
        ]);

        // Simulasikan satu pengumpulan tugas yang belum dinilai
        Pengumpulan::create([
            'tugas_id' => $tugas->id,
            'siswa_id' => $this->siswa->id,
            'link' => 'https://github.com/my-submission',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->guruUser, 'api')
            ->getJson('/api/dashboard/guru');

        $response->assertStatus(200);
        $response->assertJsonPath('data.guru_name', $this->guru->nama);
        $response->assertJsonPath('data.summary.total_materi', 1);
        $response->assertJsonPath('data.summary.total_tugas', 1);
        $response->assertJsonPath('data.summary.tugas_belum_dinilai', 1);
        $response->assertJsonPath('data.summary.total_kelas', 1);
    }

    public function test_siswa_bisa_mengakses_dashboard_siswa_dengan_ringkasan_yang_sesuai()
    {
        // Membuat Tugas baru untuk Rombel kelas siswa
        $tugas = Tugas::create([
            'judul' => 'Tugas Kimia 1',
            'deskripsi' => 'Tugas deskripsi',
            'deadline' => now()->addDays(2),
            'mapel_id' => $this->mapel->id,
            'rombel_id' => $this->rombel->id,
            'guru_id' => $this->guru->id,
        ]);

        // 1. Memeriksa dashboard saat masih ada tugas tertunda (pending tugas)
        $responsePending = $this->actingAs($this->siswaUser, 'api')
            ->getJson('/api/dashboard/siswa');

        $responsePending->assertStatus(200);
        $responsePending->assertJsonPath('data.siswa_name', $this->siswa->nama);
        $responsePending->assertJsonPath('data.summary.total_mata_pelajaran', 1);
        $responsePending->assertJsonPath('data.summary.tugas_tertunda', 1);
        $responsePending->assertJsonPath('data.summary.tugas_selesai', 0);
        $responsePending->assertJsonPath('data.notifikasi_tugas', 'Ada 1 tugas yang belum dikerjakan');

        // 2. Siswa mengumpulkan tugas dan memeriksa kembali status dashboard (tugas selesai)
        Pengumpulan::create([
            'tugas_id' => $tugas->id,
            'siswa_id' => $this->siswa->id,
            'link' => 'https://github.com/my-submission',
            'submitted_at' => now(),
        ]);

        $responseDone = $this->actingAs($this->siswaUser, 'api')
            ->getJson('/api/dashboard/siswa');

        $responseDone->assertStatus(200);
        $responseDone->assertJsonPath('data.summary.tugas_tertunda', 0);
        $responseDone->assertJsonPath('data.summary.tugas_selesai', 1);
        $responseDone->assertJsonPath('data.notifikasi_tugas', 'Semua tugas sudah dikerjakan');
    }
}
