<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Siswa;
use App\Models\Guru;
use App\Models\Rombel;
use App\Models\AnggotaKelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UserImportController extends Controller
{
    /**
     * Import Siswa dari Excel/CSV
     * 
     * Endpoint ini digunakan oleh Admin untuk mengimport data siswa secara massal dari file Excel (.xlsx, .xls) atau CSV.
     * Proses import menggunakan database transaction, sehingga jika ada satu baris yang salah, seluruh proses akan dibatalkan untuk menjaga konsistensi data.
     * 
     * **Format Template Excel (Mulai Kolom A):**
     * - **Kolom A (NIS)**: Nomor Induk Siswa. Wajib, unik, dan akan menjadi username login siswa.
     * - **Kolom B (Nama)**: Nama lengkap siswa. Wajib.
     * - **Kolom C (Jenis Kelamin)**: L/P atau Laki-laki/Perempuan. Wajib (otomatis dinormalisasi).
     * - **Kolom D (Rombel)**: ID Rombel (angka) atau Nama Rombel (misal: "X PPLG 1", case-insensitive). Wajib.
     * - **Kolom E (Password)**: Password login siswa. Wajib, minimal 6 karakter.
     * 
     * *Catatan: Baris pertama file harus berupa header (judul kolom) dan akan dilewati secara otomatis.*
     */
    public function importSiswa(Request $request)
    {
        // Meningkatkan batas waktu eksekusi agar import data besar tidak timeout (5 menit)
        set_time_limit(900);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // max 10MB
        ]);

        $file = $request->file('file');
        
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca file Excel/CSV: ' . $e->getMessage()
            ], 400);
        }

        if (count($rows) <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'File Excel kosong atau hanya berisi header.'
            ], 400);
        }

        // Ambil header dan skip
        $header = array_shift($rows);

        // Ambil semua rombel untuk pencocokan cepat
        $rombels = Rombel::with(['kelas:id,tingkat', 'jurusan:id,nama_jurusan'])->get();
        $rombelByName = [];
        $rombelById = [];

        foreach ($rombels as $r) {
            $rombelById[$r->id] = $r;
            $tingkat = $r->kelas->tingkat ?? '';
            $jurusan = $r->jurusan->nama_jurusan ?? '';
            $name = strtolower(trim($tingkat . ' ' . $jurusan));
            if ($name !== '') {
                $rombelByName[$name] = $r;
            }
        }

        $errors = [];
        $validData = [];
        $seenNis = [];

        // Validasi Baris demi Baris
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // Baris Excel dimulai dari 1, dan baris data pertama setelah header adalah 2

            // Bersihkan data baris
            $nis = isset($row[0]) ? trim((string)$row[0]) : '';
            $nama = isset($row[1]) ? trim((string)$row[1]) : '';
            $jkInput = isset($row[2]) ? trim((string)$row[2]) : '';
            $rombelInput = isset($row[3]) ? trim((string)$row[3]) : '';
            $password = isset($row[4]) ? trim((string)$row[4]) : '';

            // Jika baris kosong sepenuhnya, skip
            if (empty($nis) && empty($nama) && empty($jkInput) && empty($rombelInput) && empty($password)) {
                continue;
            }

            $rowErrors = [];

            // 1. Validasi NIS / Username
            if (empty($nis)) {
                $rowErrors[] = 'NIS wajib diisi.';
            } else {
                if (in_array($nis, $seenNis)) {
                    $rowErrors[] = 'NIS ganda dalam file Excel.';
                } else {
                    $seenNis[] = $nis;
                }

                if (User::where('username', $nis)->exists()) {
                    $rowErrors[] = "NIS/Username '{$nis}' sudah terdaftar di database.";
                }
            }

            // 2. Validasi Nama
            if (empty($nama)) {
                $rowErrors[] = 'Nama wajib diisi.';
            }

            // 3. Validasi Jenis Kelamin (Normalisasi)
            $jk = null;
            if (empty($jkInput)) {
                $rowErrors[] = 'Jenis Kelamin wajib diisi.';
            } else {
                $jkLower = strtolower($jkInput);
                if (in_array($jkLower, ['l', 'laki-laki', 'laki laki', 'laki - laki', 'pria', 'cowok'])) {
                    $jk = 'L';
                } elseif (in_array($jkLower, ['p', 'perempuan', 'wanita', 'cewek'])) {
                    $jk = 'P';
                } else {
                    $rowErrors[] = "Jenis Kelamin '{$jkInput}' tidak valid. Harus Laki-laki atau Perempuan.";
                }
            }

            // 4. Validasi Rombel
            $rombel = null;
            if (empty($rombelInput)) {
                $rowErrors[] = 'Rombel/Kelas wajib diisi.';
            } else {
                if (is_numeric($rombelInput)) {
                    $rombel = $rombelById[(int)$rombelInput] ?? null;
                } else {
                    $rombel = $rombelByName[strtolower($rombelInput)] ?? null;
                }

                if (!$rombel) {
                    $rowErrors[] = "Rombel '{$rombelInput}' tidak ditemukan. Gunakan ID Rombel yang valid atau nama Rombel yang terdaftar.";
                }
            }

            // 5. Validasi Password
            if (empty($password)) {
                $rowErrors[] = 'Password wajib diisi.';
            } elseif (strlen($password) < 6) {
                $rowErrors[] = 'Password minimal harus 6 karakter.';
            }

            // Kumpulkan error jika ada
            if (!empty($rowErrors)) {
                $errors[] = [
                    'baris' => $rowNum,
                    'nis' => $nis ?: '-',
                    'nama' => $nama ?: '-',
                    'errors' => $rowErrors
                ];
            } else {
                $validData[] = [
                    'nis' => $nis,
                    'nama' => $nama,
                    'jenis_kelamin' => $jk,
                    'rombel_id' => $rombel->id,
                    'jurusan_id' => $rombel->jurusan_id,
                    'password' => $password,
                ];
            }
        }

        // Jika ada error pada baris mana pun, gagalkan seluruh import demi konsistensi data
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Proses import dibatalkan karena terdapat data yang tidak valid.',
                'total_errors' => count($errors),
                'errors' => $errors
            ], 422);
        }

        // Lakukan penyimpanan menggunakan Database Transaction
        DB::beginTransaction();

        try {
            foreach ($validData as $data) {
                // 1. Create User
                $user = User::create([
                    'username' => $data['nis'],
                    'password' => Hash::make($data['password']),
                    'role' => 'siswa',
                ]);

                // 2. Create Siswa
                $siswa = Siswa::create([
                    'user_id' => $user->id,
                    'nis' => $data['nis'],
                    'nama' => $data['nama'],
                    'jenis_kelamin' => $data['jenis_kelamin'],
                    'jurusan_id' => $data['jurusan_id'],
                ]);

                // 3. Create AnggotaKelas
                AnggotaKelas::create([
                    'siswa_id' => $siswa->id,
                    'rombel_id' => $data['rombel_id'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil mengimport ' . count($validData) . ' data siswa.',
                'data' => [
                    'total_imported' => count($validData)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import Guru dari Excel/CSV
     * 
     * Endpoint ini digunakan oleh Admin untuk mengimport data guru secara massal dari file Excel (.xlsx, .xls) atau CSV.
     * Proses import menggunakan database transaction, sehingga jika ada satu baris yang salah, seluruh proses akan dibatalkan untuk menjaga konsistensi data.
     * 
     * **Format Template Excel (Mulai Kolom A):**
     * - **Kolom A (NIK)**: Nomor Induk Karyawan/Guru. Wajib, unik, dan akan menjadi username login guru.
     * - **Kolom B (Nama)**: Nama lengkap guru beserta gelar. Wajib.
     * - **Kolom C (Jenis Kelamin)**: L/P atau Laki-laki/Perempuan. Wajib (otomatis dinormalisasi).
     * - **Kolom D (Password)**: Password login guru. Wajib, minimal 6 karakter.
     * 
     * *Catatan: Baris pertama file harus berupa header (judul kolom) dan akan dilewati secara otomatis.*
     */
    public function importGuru(Request $request)
    {
        // Meningkatkan batas waktu eksekusi agar import data besar tidak timeout (5 menit)
        set_time_limit(300);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // max 10MB
        ]);

        $file = $request->file('file');
        
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca file Excel/CSV: ' . $e->getMessage()
            ], 400);
        }

        if (count($rows) <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'File Excel kosong atau hanya berisi header.'
            ], 400);
        }

        // Ambil header dan skip
        $header = array_shift($rows);

        $errors = [];
        $validData = [];
        $seenNik = [];

        // Validasi Baris demi Baris
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            // Bersihkan data baris
            $nik = isset($row[0]) ? trim((string)$row[0]) : '';
            $nama = isset($row[1]) ? trim((string)$row[1]) : '';
            $jkInput = isset($row[2]) ? trim((string)$row[2]) : '';
            $password = isset($row[3]) ? trim((string)$row[3]) : '';

            // Jika baris kosong sepenuhnya, skip
            if (empty($nik) && empty($nama) && empty($jkInput) && empty($password)) {
                continue;
            }

            $rowErrors = [];

            // 1. Validasi NIK / Username
            if (empty($nik)) {
                $rowErrors[] = 'NIK wajib diisi.';
            } else {
                if (in_array($nik, $seenNik)) {
                    $rowErrors[] = 'NIK ganda dalam file Excel.';
                } else {
                    $seenNik[] = $nik;
                }

                if (User::where('username', $nik)->exists()) {
                    $rowErrors[] = "NIK/Username '{$nik}' sudah terdaftar di database.";
                }
            }

            // 2. Validasi Nama
            if (empty($nama)) {
                $rowErrors[] = 'Nama wajib diisi.';
            }

            // 3. Validasi Jenis Kelamin (Normalisasi)
            $jk = null;
            if (empty($jkInput)) {
                $rowErrors[] = 'Jenis Kelamin wajib diisi.';
            } else {
                $jkLower = strtolower($jkInput);
                if (in_array($jkLower, ['l', 'laki-laki', 'laki laki', 'laki - laki', 'pria', 'cowok'])) {
                    $jk = 'Laki-laki';
                } elseif (in_array($jkLower, ['p', 'perempuan', 'wanita', 'cewek'])) {
                    $jk = 'Perempuan';
                } else {
                    $rowErrors[] = "Jenis Kelamin '{$jkInput}' tidak valid. Harus Laki-laki atau Perempuan.";
                }
            }

            // 4. Validasi Password
            if (empty($password)) {
                $rowErrors[] = 'Password wajib diisi.';
            } elseif (strlen($password) < 6) {
                $rowErrors[] = 'Password minimal harus 6 karakter.';
            }

            // Kumpulkan error jika ada
            if (!empty($rowErrors)) {
                $errors[] = [
                    'baris' => $rowNum,
                    'nik' => $nik ?: '-',
                    'nama' => $nama ?: '-',
                    'errors' => $rowErrors
                ];
            } else {
                $validData[] = [
                    'nik' => $nik,
                    'nama' => $nama,
                    'jenis_kelamin' => $jk,
                    'password' => $password,
                ];
            }
        }

        // Jika ada error pada baris mana pun, gagalkan seluruh import demi konsistensi data
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Proses import dibatalkan karena terdapat data yang tidak valid.',
                'total_errors' => count($errors),
                'errors' => $errors
            ], 422);
        }

        // Lakukan penyimpanan menggunakan Database Transaction
        DB::beginTransaction();

        try {
            foreach ($validData as $data) {
                // 1. Create User
                $user = User::create([
                    'username' => $data['nik'],
                    'password' => Hash::make($data['password']),
                    'role' => 'guru',
                ]);

                // 2. Create Guru
                Guru::create([
                    'user_id' => $user->id,
                    'nik' => $data['nik'],
                    'nama' => $data['nama'],
                    'jenis_kelamin' => $data['jenis_kelamin'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil mengimport ' . count($validData) . ' data guru.',
                'data' => [
                    'total_imported' => count($validData)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }
}
