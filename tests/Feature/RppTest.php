<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Guru;
use App\Models\MataPelajaran;
use App\Models\Rpp;

class RppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Since database connection might not be available, skip these tests if DB is down
        try {
            \DB::connection()->getPdo();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database connection not available.');
        }
    }

    public function test_guru_can_create_rpp()
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'guru']);
        $guru = Guru::factory()->create(['user_id' => $user->id]);
        $mapel = MataPelajaran::factory()->create();

        $token = auth('api')->login($user);

        $file = UploadedFile::fake()->create('rpp_doc.pdf', 100, 'application/pdf');

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
        ])->postJson('/api/rpp', [
            'judul' => 'RPP Bab 1',
            'deskripsi' => 'Deskripsi RPP Bab 1',
            'mapel_id' => $mapel->id,
            'files' => [$file],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('rpps', [
            'judul' => 'RPP Bab 1',
            'guru_id' => $guru->id,
            'mapel_id' => $mapel->id,
        ]);
        $this->assertDatabaseHas('rpp_files', [
            'nama_file' => 'rpp_doc.pdf',
            'tipe' => 'PDF',
        ]);
    }
}
