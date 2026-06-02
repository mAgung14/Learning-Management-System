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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MateriTest extends TestCase
{
    use RefreshDatabase;

    private $guruUser;
    private $guru;
    private $siswaUser;
    private $mapel;
    private $rombel;

    protected function setUp(): void
    {
        parent::setUp();

        $kelas = Kelas::create(['tingkat' => 'X', 'tahun_ajaran' => '2025/2026']);
        $jurusan = Jurusan::create(['nama_jurusan' => 'PPLG']);
        $this->rombel = Rombel::create(['kelas_id' => $kelas->id, 'jurusan_id' => $jurusan->id]);

        // Setup Akun Guru
        $this->guruUser = User::create([
            'username' => 'guru_materi',
            'password' => bcrypt('password123'),
            'role' => 'guru',
        ]);
        $this->guru = Guru::create([
            'user_id' => $this->guruUser->id,
            'nik' => '98888',
            'nama' => 'Guru Materi',
            'jenis_kelamin' => 'Perempuan',
        ]);

        // Setup Akun Siswa
        $this->siswaUser = User::create([
            'username' => 'siswa_materi',
            'password' => bcrypt('password123'),
            'role' => 'siswa',
        ]);

        $this->mapel = MataPelajaran::create([
            'nama_mapel' => 'Bahasa Indonesia',
            'kode_mapel' => 'IND01',
        ]);
    }

    public function test_guru_bisa_membuat_materi_dengan_file_dan_youtube()
    {
        Storage::fake('public');

        // Menyiapkan tiruan file upload (PDF dan Gambar)
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $image = UploadedFile::fake()->image('diagram.png');

        // Guru memposting materi baru
        $response = $this->actingAs($this->guruUser, 'api')
            ->postJson('/api/guru/materi', [
                'judul' => 'Bab 1: Teks Prosedur',
                'deskripsi' => 'Materi tentang teks prosedur',
                'mapel_id' => $this->mapel->id,
                'rombel_id' => $this->rombel->id,
                'file1' => $file,
                'files' => [$image],
                'youtube_urls' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ']
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Materi berhasil dibuat');
        $materiId = $response->json('data.id');

        $this->assertDatabaseHas('materi', [
            'id' => $materiId,
            'judul' => 'Bab 1: Teks Prosedur',
            'guru_id' => $this->guru->id,
        ]);

        // Memastikan record file materi tersimpan dengan tipe yang sesuai di database
        $this->assertDatabaseHas('file_material', [
            'materi_id' => $materiId,
            'tipe' => 'PDF',
            'nama_file' => 'document.pdf',
        ]);

        $this->assertDatabaseHas('file_material', [
            'materi_id' => $materiId,
            'tipe' => 'IMAGE',
            'nama_file' => 'diagram.png',
        ]);

        $this->assertDatabaseHas('file_material', [
            'materi_id' => $materiId,
            'tipe' => 'YOUTUBE',
            'url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ]);
    }

    public function test_guru_bisa_membaca_dan_memperbarui_materi()
    {
        Storage::fake('public');

        $materi = Materi::create([
            'judul' => 'Bab 2: Puisi',
            'deskripsi' => 'Belajar puisi',
            'mapel_id' => $this->mapel->id,
            'guru_id' => $this->guru->id,
        ]);

        // Detail Tampilan Materi (Read / Show)
        $responseShow = $this->actingAs($this->guruUser, 'api')
            ->getJson("/api/guru/materi/{$materi->id}");
        $responseShow->assertStatus(200);
        $responseShow->assertJsonPath('data.judul', 'Bab 2: Puisi');

        // Memperbarui Materi (Update)
        $responseUpdate = $this->actingAs($this->guruUser, 'api')
            ->putJson("/api/guru/materi/{$materi->id}", [
                'judul' => 'Bab 2: Puisi Modern',
                'deskripsi' => 'Puisi kontemporer',
            ]);

        $responseUpdate->assertStatus(200);
        $this->assertDatabaseHas('materi', [
            'id' => $materi->id,
            'judul' => 'Bab 2: Puisi Modern',
        ]);
    }

    public function test_guru_bisa_menghapus_materi()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('lesson.pdf', 50, 'application/pdf');

        // Memposting materi baru
        $responseCreate = $this->actingAs($this->guruUser, 'api')
            ->postJson('/api/guru/materi', [
                'judul' => 'Materi untuk Dihapus',
                'deskripsi' => 'Deskripsi materi',
                'mapel_id' => $this->mapel->id,
                'file1' => $file,
            ]);

        $materiId = $responseCreate->json('data.id');

        // Menghapus materi
        $responseDelete = $this->actingAs($this->guruUser, 'api')
            ->deleteJson("/api/guru/materi/{$materiId}");

        $responseDelete->assertStatus(200);
        $responseDelete->assertJsonPath('message', 'Materi dan file terkait berhasil dihapus');

        // Memastikan data terhapus di database
        $this->assertDatabaseMissing('materi', ['id' => $materiId]);
        $this->assertDatabaseMissing('file_material', ['materi_id' => $materiId]);
    }

    public function test_siswa_tidak_bisa_membuat_materi()
    {
        // Memastikan siswa dilarang memposting materi
        $response = $this->actingAs($this->siswaUser, 'api')
            ->postJson('/api/guru/materi', [
                'judul' => 'Judul Siswa',
                'deskripsi' => 'Deskripsi Siswa',
                'mapel_id' => $this->mapel->id,
            ]);

        $response->assertStatus(403);
    }
}
