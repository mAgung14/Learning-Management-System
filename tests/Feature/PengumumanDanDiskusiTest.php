<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Guru;
use App\Models\Siswa;
use App\Models\MataPelajaran;
use App\Models\Rombel;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\Pengumuman;
use App\Models\Diskusi;
use App\Events\PengumumanCreated;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengumumanDanDiskusiTest extends TestCase
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

        // Setup Akun Guru
        $this->guruUser = User::create([
            'username' => 'guru_chat',
            'password' => bcrypt('password123'),
            'role' => 'guru',
        ]);
        $this->guru = Guru::create([
            'user_id' => $this->guruUser->id,
            'nik' => '94444',
            'nama' => 'Guru Chat',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        // Setup Akun Siswa
        $this->siswaUser = User::create([
            'username' => 'siswa_chat',
            'password' => bcrypt('password123'),
            'role' => 'siswa',
        ]);
        $this->siswa = Siswa::create([
            'user_id' => $this->siswaUser->id,
            'nis' => '94445',
            'nama' => 'Siswa Chat',
            'jenis_kelamin' => 'Perempuan',
        ]);

        // Tambahkan Siswa ke Rombel
        \DB::table('anggota_kelas')->insert([
            'rombel_id' => $this->rombel->id,
            'siswa_id' => $this->siswa->id,
        ]);

        // Setup Mata Pelajaran
        $this->mapel = MataPelajaran::create([
            'nama_mapel' => 'Sejarah',
            'kode_mapel' => 'SEJ01',
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

    public function test_guru_bisa_melakukan_crud_pengumuman_beserta_broadcastnya()
    {
        // Memalsukan event dispatcher agar tidak melakukan broadcast real-time sebenarnya
        Event::fake();

        // 1. Buat Pengumuman Baru (Create)
        $responseCreate = $this->actingAs($this->guruUser, 'api')
            ->postJson('/api/pengumuman', [
                'judul' => 'Pengumuman Penting',
                'deskripsi' => 'Ujian akhir semester ditunda',
                'mapel_id' => $this->mapel->id,
            ]);

        $responseCreate->assertStatus(201);
        $pengumumanId = $responseCreate->json('data.id');

        $this->assertDatabaseHas('pengumuman', [
            'id' => $pengumumanId,
            'judul' => 'Pengumuman Penting',
            'user_id' => $this->guruUser->id,
        ]);

        // Memastikan event broadcast PengumumanCreated telah terpanggil
        Event::assertDispatched(PengumumanCreated::class);

        // 2. Baca Semua Pengumuman (Read / Index)
        $responseIndex = $this->actingAs($this->siswaUser, 'api')
            ->getJson('/api/pengumuman');
        $responseIndex->assertStatus(200);
        $responseIndex->assertJsonCount(1, 'data');

        // 3. Detail Pengumuman (Read / Show)
        $responseShow = $this->actingAs($this->siswaUser, 'api')
            ->getJson("/api/pengumuman/{$pengumumanId}");
        $responseShow->assertStatus(200);
        $responseShow->assertJsonPath('data.judul', 'Pengumuman Penting');

        // 4. Perbarui Pengumuman (Update)
        $responseUpdate = $this->actingAs($this->guruUser, 'api')
            ->putJson("/api/pengumuman/{$pengumumanId}", [
                'judul' => 'Pengumuman Sangat Penting',
            ]);
        $responseUpdate->assertStatus(200);
        $this->assertDatabaseHas('pengumuman', [
            'id' => $pengumumanId,
            'judul' => 'Pengumuman Sangat Penting',
        ]);

        // 5. Hapus Pengumuman (Destroy)
        $responseDelete = $this->actingAs($this->guruUser, 'api')
            ->deleteJson("/api/pengumuman/{$pengumumanId}");
        $responseDelete->assertStatus(200);
        $this->assertDatabaseMissing('pengumuman', ['id' => $pengumumanId]);
    }

    public function test_user_bisa_mengirim_dan_menerima_pesan_di_forum_diskusi()
    {
        Event::fake();

        // 1. Membaca pesan diskusi yang masih kosong (Get empty)
        $responseIndexEmpty = $this->actingAs($this->siswaUser, 'api')
            ->getJson("/api/diskusi/{$this->mapel->id}");
        $responseIndexEmpty->assertStatus(200);
        $responseIndexEmpty->assertJsonCount(0, 'data');

        // 2. Siswa mengirimkan pesan baru (Post message)
        $responsePost = $this->actingAs($this->siswaUser, 'api')
            ->postJson("/api/diskusi/{$this->mapel->id}", [
                'pesan' => 'Halo teman-teman, apakah ada PR hari ini?',
            ]);

        $responsePost->assertStatus(201);
        $responsePost->assertJsonPath('status', 'success');
        $responsePost->assertJsonPath('data.nama_pengirim', $this->siswa->nama);
        $responsePost->assertJsonPath('data.role', 'siswa');

        // Memastikan event broadcast MessageSent telah terpanggil
        Event::assertDispatched(MessageSent::class);

        // 3. Guru mengirimkan pesan balasan (Post reply)
        $responsePostGuru = $this->actingAs($this->guruUser, 'api')
            ->postJson("/api/diskusi/{$this->mapel->id}", [
                'pesan' => 'PR ada di halaman 15 ya',
            ]);
        $responsePostGuru->assertStatus(201);
        $responsePostGuru->assertJsonPath('data.nama_pengirim', $this->guru->nama);
        $responsePostGuru->assertJsonPath('data.role', 'guru');

        // 4. Membaca kembali seluruh pesan (Get messages)
        $responseIndex = $this->actingAs($this->siswaUser, 'api')
            ->getJson("/api/diskusi/{$this->mapel->id}");
        $responseIndex->assertStatus(200);
        $responseIndex->assertJsonCount(2, 'data');
    }

    public function test_user_bisa_menghapus_pesan_di_forum_diskusi()
    {
        Event::fake();

        // 1. Buat pesan diskusi siswa & guru
        $diskusiSiswa = Diskusi::create([
            'mata_pelajaran_id' => $this->mapel->id,
            'user_id' => $this->siswaUser->id,
            'pesan' => 'Pesan siswa untuk dihapus',
        ]);

        $diskusiGuru = Diskusi::create([
            'mata_pelajaran_id' => $this->mapel->id,
            'user_id' => $this->guruUser->id,
            'pesan' => 'Pesan guru untuk dihapus',
        ]);

        // 2. Siswa mencoba menghapus pesan milik orang lain (Guru) -> Dilarang (403)
        $responseDeleteForbidden = $this->actingAs($this->siswaUser, 'api')
            ->deleteJson("/api/diskusi/{$diskusiGuru->id}");
        $responseDeleteForbidden->assertStatus(403);
        $this->assertDatabaseHas('diskusis', ['id' => $diskusiGuru->id]);

        // 3. Siswa menghapus pesannya sendiri -> Sukses (200)
        $responseDeleteSiswa = $this->actingAs($this->siswaUser, 'api')
            ->deleteJson("/api/diskusi/{$diskusiSiswa->id}");
        $responseDeleteSiswa->assertStatus(200);
        $responseDeleteSiswa->assertJsonPath('status', 'success');
        $this->assertDatabaseMissing('diskusis', ['id' => $diskusiSiswa->id]);

        // Memastikan event broadcast MessageDeleted telah dipanggil
        Event::assertDispatched(\App\Events\MessageDeleted::class);

        // 4. Guru menghapus pesannya sendiri -> Sukses (200)
        $responseDeleteGuru = $this->actingAs($this->guruUser, 'api')
            ->deleteJson("/api/diskusi/{$diskusiGuru->id}");
        $responseDeleteGuru->assertStatus(200);
        $this->assertDatabaseMissing('diskusis', ['id' => $diskusiGuru->id]);

        // 5. Guru menghapus pesan siswa lain (Moderasi) -> Sukses (200)
        $diskusiSiswa2 = Diskusi::create([
            'mata_pelajaran_id' => $this->mapel->id,
            'user_id' => $this->siswaUser->id,
            'pesan' => 'Pesan siswa 2 untuk dimoderasi',
        ]);

        $responseDeleteModeration = $this->actingAs($this->guruUser, 'api')
            ->deleteJson("/api/diskusi/{$diskusiSiswa2->id}");
        $responseDeleteModeration->assertStatus(200);
        $this->assertDatabaseMissing('diskusis', ['id' => $diskusiSiswa2->id]);
    }
}
