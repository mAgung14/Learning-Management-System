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
use App\Events\ForumMessageSent;
use App\Http\Controllers\BroadcastingAuthController;

// Broadcasting Auth Route (Private Channels)
// Uses a custom controller instead of Broadcast::routes() to guarantee a valid
// JSON response is returned, fixing the Echo auth SyntaxError with Reverb.
Route::post('/broadcasting/auth', [BroadcastingAuthController::class, 'authorize'])->middleware('auth:api');


// Cek apakah ada view welcome
Route::get('/', function () {

    return view('welcome');
})->name('home');

Route::get('/test-reverb', function () {

    event(new ForumMessageSent([
        'message' => 'Halo dari Railway'
    ]));

    return 'ok';
});
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

Route::middleware(['auth:api', 'role:guru'])->group(function () {
    Route::get('/dashboard/guru', [DashboardController::class, 'guruDashboard']);
    Route::get('/guru/mata-pelajaran', [GuruController::class, 'mataPelajaran']);
    Route::get('/guru/profile', [GuruController::class, 'getProfile']);
    Route::put('/guru/password', [GuruController::class, 'updatePassword']);
    Route::put('/guru/profile', [GuruController::class, 'updateProfile']);
    Route::get('/guru/siswa', [SiswaController::class, 'forGuru']);
    Route::apiResource('guru/materi', MateriController::class);
    Route::apiResource('pengumuman', PengumumanController::class)->except(['index', 'show']);
    Route::get('tugas/form-data', [TugasController::class, 'formData']);
    Route::get('tugas/{id}/pengumpulan', [TugasController::class, 'pengumpulanByTugas']);
    Route::post('pengumpulan/{pengumpulanId}/nilai', [TugasController::class, 'berikanNilai']);
    Route::apiResource('tugas-susulan', \App\Http\Controllers\TugasSusulanController::class)->only(['index', 'store', 'destroy']);
    Route::apiResource('tugas', TugasController::class);
    Route::get('anggota-kelas', [AnggotaKelasController::class, 'index']);

});

Route::middleware(['auth:api', 'role:siswa'])->group(function () {
    Route::get('/dashboard/siswa', [DashboardController::class, 'siswaDashboard']);
    Route::get('/siswa/mata-pelajaran', [SiswaController::class, 'mataPelajaran']);
    Route::get('/siswa/mata-pelajaran/{id}', [SiswaController::class, 'detailMataPelajaran']);
    Route::get('/siswa/mata-pelajaran/{id}/tugas', [SiswaController::class, 'tugasMataPelajaran']);
    Route::get('/siswa/tugas-susulan', [\App\Http\Controllers\TugasSusulanController::class, 'siswaIndex']);
    Route::get('/siswa/tugas-susulan/{id}', [\App\Http\Controllers\TugasSusulanController::class, 'siswaShow']);
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
    Route::post('/guru/{id}/reset-password', [GuruController::class, 'resetPassword']);
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

    // Rute administratif Guru-Mapel
    Route::post('/guru/{id}/assign-mapel', [GuruMapelController::class, 'assignMapel']);
    Route::get('/{id}/mapel', [GuruMapelController::class, 'getMapel']);
    Route::delete('/guru/{id}/mapel/{mapel_id}', [GuruMapelController::class, 'removeMapel']);
});

Route::middleware(['auth:api'])->group(function () {
    // Diskusi
    Route::get('/diskusi/{mapel_id}', [\App\Http\Controllers\DiskusiController::class, 'index']);
    Route::post('/diskusi/{mapel_id}', [\App\Http\Controllers\DiskusiController::class, 'store']);
    Route::delete('/diskusi/{id}', [\App\Http\Controllers\DiskusiController::class, 'destroy']);

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