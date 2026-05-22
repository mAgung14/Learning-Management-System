<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\MataPelajaranController;
use App\Http\Controllers\MateriController;
use App\Http\Controllers\TugasController;
use App\Http\Controllers\PengumpulanController;
use App\Http\Controllers\NilaiController;
use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\JurusanController;
use App\Http\Controllers\AnggotaKelasController;
use App\Http\Controllers\RombelController;
use App\Http\Controllers\GuruMapelController;
use App\Http\Controllers\PengumumanController;

use Illuminate\Support\Facades\Broadcast;

// Broadcasting Auth Route (Private Channels)
Broadcast::routes(['middleware' => ['auth:api']]);

// Cek apakah ada view welcome
Route::get('/', function () {
    return view('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| API AUTH ROUTES (Public - JWT Token Auth)
|--------------------------------------------------------------------------
*/
Route::withoutMiddleware('web')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:api'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});

Route::middleware(['auth:api', 'role:admin'])
    ->get('/dashboard/summary', [DashboardController::class, 'summary']);



Route::get('/jurusan', [DashboardController::class, 'getJurusan']);
Route::get('/kelas', [DashboardController::class, 'getKelas']);

// Middleware Guru (Ditaruh di atas agar tidak bertabrakan dengan Route::apiResource('guru'))
Route::middleware(['auth:api', 'role:guru'])->group(function () {
    Route::get('/dashboard/guru', [DashboardController::class, 'guruDashboard']);
    Route::get('/guru/mata-pelajaran', [GuruController::class, 'mataPelajaran']);
    Route::get('/guru/profile', [GuruController::class, 'getProfile']);
    Route::put('/guru/password', [GuruController::class, 'updatePassword']);
    Route::get('/guru/siswa', [SiswaController::class, 'forGuru']);
    Route::apiResource('guru/materi', MateriController::class);
    Route::apiResource('pengumuman', PengumumanController::class)->except(['index', 'show']);
    Route::get('tugas/form-data', [TugasController::class, 'formData']);
    Route::get('tugas/{id}/pengumpulan', [TugasController::class, 'pengumpulanByTugas']);
    Route::post('pengumpulan/{pengumpulanId}/nilai', [TugasController::class, 'berikanNilai']);
    Route::apiResource('tugas', TugasController::class);
    Route::get('anggota-kelas', [AnggotaKelasController::class, 'index']);

    // Bank Soal — AI Generator (Structured)
    Route::post('bank-soal/generate', [\App\Http\Controllers\BankSoalController::class, 'generate']);
    Route::get('bank-soal/status/{logId}', [\App\Http\Controllers\BankSoalController::class, 'status']);
    Route::post('bank-soal/publish', [\App\Http\Controllers\BankSoalController::class, 'publish']);
    Route::get('bank-soal/logs', [\App\Http\Controllers\BankSoalController::class, 'logs']);
    Route::apiResource('bank-soal', \App\Http\Controllers\BankSoalController::class)->only(['index', 'show', 'update', 'destroy']);

    // AI Helper (Unstructured / Text Generation)
    Route::post('ai/generate-deskripsi', [\App\Http\Controllers\AiController::class, 'generateDeskripsi']);
});

Route::middleware(['auth:api', 'role:siswa'])->group(function () {
    Route::get('/dashboard/siswa', [DashboardController::class, 'siswaDashboard']);
    Route::get('/siswa/mata-pelajaran', [SiswaController::class, 'mataPelajaran']);
    Route::get('/siswa/mata-pelajaran/{id}', [SiswaController::class, 'detailMataPelajaran']);
    Route::get('/siswa/mata-pelajaran/{id}/tugas', [SiswaController::class, 'tugasMataPelajaran']);
    Route::get('/siswa/tugas/{tugasId}', [SiswaController::class, 'detailTugas']);
    Route::post('/pengumpulan', [PengumpulanController::class, 'store']);
    Route::delete('/siswa/pengumpulan/{id}', [PengumpulanController::class, 'batal']);
    Route::get('/siswa/profile', [SiswaController::class, 'getProfile']);
    Route::put('/siswa/password', [SiswaController::class, 'updatePassword']);
});

Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::get('/register-form', [AuthController::class, 'registerForm']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/siswa/import', [\App\Http\Controllers\UserImportController::class, 'importSiswa']);
    Route::post('/guru/import', [\App\Http\Controllers\UserImportController::class, 'importGuru']);
    Route::apiResource('kelas', KelasController::class);
    Route::get('/mata-pelajaran/form-data', [MataPelajaranController::class, 'formData']);
    Route::apiResource('mata-pelajaran', MataPelajaranController::class);
    Route::apiResource('siswa', SiswaController::class);
    Route::post('/siswa/{id}/reset-password', [SiswaController::class, 'resetPassword']);
    Route::apiResource('guru', GuruController::class);
    Route::apiResource('users', UserController::class)->only(['index', 'store']);
    Route::apiResource('jurusan', JurusanController::class);
    // Rombel — CRUD + assign siswa + kick siswa + assign mapel
    Route::get('/rombel/form-data', [RombelController::class, 'formData']); // ← harus sebelum apiResource
    Route::apiResource('rombel', RombelController::class);
    Route::post('/rombel/{id}/assign', [RombelController::class, 'assign']);
    Route::delete('/rombel/{id}/kick/{siswa_id}', [RombelController::class, 'kick']);
    Route::post('/rombel/{id}/assign-mapel', [RombelController::class, 'assignMapel']);
    Route::get('/rombel/{id}/mapel', [RombelController::class, 'getMapel']);
    Route::post('/rombel/promote', [RombelController::class, 'promote']);
    Route::post('/rombel/graduate', [RombelController::class, 'graduate']);
    Route::get('/mapel/filter', [MataPelajaranController::class, 'filterMapel']);

    // Anggota Kelas — ringkasan & assign manual
    Route::get('/anggota-kelas', [AnggotaKelasController::class, 'index']);
    Route::post('/anggota-kelas', [AnggotaKelasController::class, 'store']);
    Route::delete('/anggota-kelas/{id}', [AnggotaKelasController::class, 'destroy']);

    // Pengumpulan (Admin dapat melihat dan menghapus)
    Route::get('/pengumpulan', [PengumpulanController::class, 'index']);
    Route::get('/pengumpulan/{id}', [PengumpulanController::class, 'show']);
    Route::delete('/pengumpulan/{id}', [PengumpulanController::class, 'destroy']);
});

Route::prefix('guru')->group(function () {
    Route::post('/{id}/assign-mapel', [GuruMapelController::class, 'assignMapel']);
    Route::get('/{id}/mapel', [GuruMapelController::class, 'getMapel']);
    Route::delete('/{id}/mapel/{mapel_id}', [GuruMapelController::class, 'removeMapel']);
    Route::put('/guru/profile', [GuruController::class, 'updateProfile']);
});

Route::middleware(['auth:api'])->group(function () {
    // Diskusi
    Route::get('/diskusi/{mapel_id}', [\App\Http\Controllers\DiskusiController::class, 'index']);
    Route::post('/diskusi/{mapel_id}', [\App\Http\Controllers\DiskusiController::class, 'store']);

    // Pengumuman (Bisa diakses siswa dan guru)
    Route::get('/pengumuman', [PengumumanController::class, 'index']);
    Route::get('/pengumuman/{pengumuman}', [PengumumanController::class, 'show']);

    // Pengumpulan update
    Route::put('/pengumpulan/{id}', [PengumpulanController::class, 'update']);
    // Test route untuk broadcast pengumuman
    Route::post('/test-broadcast', function () {
        $pengumuman = \App\Models\Pengumuman::create([
            'judul' => 'Test Pengumuman Realtime',
            'deskripsi' => 'Ini adalah pengumuman test untuk memastikan broadcast bekerja',
            'user_id' => auth()->id(),
        ]);

        \App\Events\PengumumanCreated::dispatch($pengumuman);

        return response()->json(['message' => 'Test broadcast sent']);
    });
});


// Moved to the top