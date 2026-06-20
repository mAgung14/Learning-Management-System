# Dokumentasi API - LMS-KP (Learning Management System)

Dokumentasi ini berisi daftar lengkap endpoint API pada project LMS-KP Backend yang dikelompokkan berdasarkan modul/fitur. Secara default, seluruh rute API yang didefinisikan di `routes/api.php` memiliki prefix `/api` (misalnya: `/api/login`).

Terdapat juga dokumentasi interaktif (OpenAPI/Swagger) yang disediakan oleh package Scramble pada:
- **Dokumentasi UI (Interactive Docs):** `/docs/api`
- **JSON Specification File:** `/docs/api.json`

---

## 1. Otentikasi (Authentication) & Data User (`AuthController`)

| Method | Endpoint | Handler | Middleware / Akses | Fungsi |
| :--- | :--- | :--- | :--- | :--- |
| **POST** | `/api/login` | `AuthController@login` | Public | Login untuk mendapatkan token JWT (Bearer). |
| **POST** | `/api/logout` | `AuthController@logout` | `auth:api` | Logout dan menghapus validitas token JWT saat ini. |
| **POST** | `/api/refresh` | `AuthController@refresh` | `auth:api` | Memperbarui (refresh) token JWT yang hampir kedaluwarsa. |
| **GET** | `/api/me` | `AuthController@me` | `auth:api` | Mendapatkan data profil user yang sedang login saat ini. |
| **GET** | `/api/register-form` | `AuthController@registerForm` | `auth:api`, `role:admin` | Mengambil data pendukung registrasi (rombel, jurusan, kelas). |
| **POST** | `/api/register` | `AuthController@register` | `auth:api`, `role:admin` | Mendaftarkan pengguna baru (Admin, Guru, atau Siswa). |

---

## 2. Dashboard (`DashboardController`)

| Method | Endpoint | Handler | Middleware / Akses | Fungsi |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/dashboard/summary` | `DashboardController@summary` | `auth:api`, `role:admin` | Mengambil ringkasan statistik data untuk dashboard Admin. |
| **GET** | `/api/dashboard/guru` | `DashboardController@guruDashboard` | `auth:api`, `role:guru` | Mengambil statistik dan jadwal mengajar untuk dashboard Guru. |
| **GET** | `/api/dashboard/siswa` | `DashboardController@siswaDashboard` | `auth:api`, `role:siswa` | Mengambil data progres belajar dan tugas untuk dashboard Siswa. |
| **GET** | `/api/jurusan` | `DashboardController@getJurusan` | Public | Mengambil daftar semua jurusan secara ringkas. |
| **GET** | `/api/kelas` | `DashboardController@getKelas` | Public | Mengambil daftar semua tingkatan kelas secara ringkas. |

---

## 3. Modul Guru (Teacher Management)

| Method | Endpoint | Handler | Middleware / Akses | Fungsi |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/guru` | `GuruController@index` | `auth:api`, `role:admin` | Menampilkan seluruh daftar guru. |
| **POST** | `/api/guru` | `GuruController@store` | `auth:api`, `role:admin` | Membuat data guru baru. |
| **GET** | `/api/guru/{guru}` | `GuruController@show` | `auth:api`, `role:admin` | Menampilkan detail data guru berdasarkan ID. |
| **PUT/PATCH** | `/api/guru/{guru}` | `GuruController@update` | `auth:api`, `role:admin` | Memperbarui data guru berdasarkan ID. |
| **DELETE** | `/api/guru/{guru}` | `GuruController@destroy` | `auth:api`, `role:admin` | Menghapus data guru berdasarkan ID. |
| **POST** | `/api/guru/import` | `UserImportController@importGuru` | `auth:api`, `role:admin` | Mengimpor data guru secara massal menggunakan file Excel/CSV. |
| **POST** | `/api/guru/{id}/reset-password` | `GuruController@resetPassword` | `auth:api`, `role:admin` | Mengatur ulang (reset) kata sandi guru tertentu. |
| **GET** | `/api/guru/profile` | `GuruController@getProfile` | `auth:api`, `role:guru` | Mengambil detail profil guru yang sedang login. |
| **PUT** | `/api/guru/profile` | `GuruController@updateProfile` | `auth:api`, `role:guru` | Mengubah detail profil guru yang sedang login. |
| **PUT** | `/api/guru/password` | `GuruController@updatePassword` | `auth:api`, `role:guru` | Mengubah kata sandi guru yang sedang login. |
| **GET** | `/api/guru/mata-pelajaran` | `GuruController@mataPelajaran` | `auth:api`, `role:guru` | Menampilkan mata pelajaran yang diampu oleh guru yang sedang login. |
| **GET** | `/api/guru/siswa` | `SiswaController@forGuru` | `auth:api`, `role:guru` | Menampilkan daftar siswa yang berada di bawah bimbingan guru. |
| **POST** | `/api/guru/{id}/assign-mapel` | `GuruMapelController@assignMapel` | `auth:api`, `role:admin` | Menugaskan mata pelajaran ke guru tertentu. |
| **GET** | `/api/{id}/mapel` | `GuruMapelController@getMapel` | `auth:api`, `role:admin` | Menampilkan daftar mata pelajaran yang ditugaskan ke guru tertentu. |
| **DELETE** | `/api/guru/{id}/mapel/{mapel_id}` | `GuruMapelController@removeMapel` | `auth:api`, `role:admin` | Menghapus penugasan mata pelajaran dari guru tertentu. |

---

## 4. Modul Siswa (Student Management)

| Method | Endpoint | Handler | Middleware / Akses | Fungsi |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/siswa` | `SiswaController@index` | `auth:api`, `role:admin` | Menampilkan seluruh daftar siswa. |
| **POST** | `/api/siswa` | `SiswaController@store` | `auth:api`, `role:admin` | Membuat data siswa baru. |
| **GET** | `/api/siswa/{siswa}` | `SiswaController@show` | `auth:api`, `role:admin` | Menampilkan detail data siswa berdasarkan ID. |
| **PUT/PATCH** | `/api/siswa/{siswa}` | `SiswaController@update` | `auth:api`, `role:admin` | Memperbarui data siswa berdasarkan ID. |
| **DELETE** | `/api/siswa/{siswa}` | `SiswaController@destroy` | `auth:api`, `role:admin` | Menghapus data siswa berdasarkan ID. |
| **POST** | `/api/siswa/import` | `UserImportController@importSiswa` | `auth:api`, `role:admin` | Mengimpor data siswa secara massal menggunakan file Excel/CSV. |
| **POST** | `/api/siswa/{id}/reset-password` | `SiswaController@resetPassword` | `auth:api`, `role:admin` | Mengatur ulang (reset) kata sandi siswa tertentu. |
| **GET** | `/api/siswa/profile` | `SiswaController@getProfile` | `auth:api`, `role:siswa` | Mengambil detail profil siswa yang sedang login. |
| **PUT** | `/api/siswa/password` | `SiswaController@updatePassword` | `auth:api`, `role:siswa` | Mengubah kata sandi siswa yang sedang login. |
| **GET** | `/api/siswa/mata-pelajaran` | `SiswaController@mataPelajaran` | `auth:api`, `role:siswa` | Menampilkan semua mata pelajaran yang diambil siswa yang sedang login. |
| **GET** | `/api/siswa/mata-pelajaran/{id}` | `SiswaController@detailMataPelajaran` | `auth:api`, `role:siswa` | Menampilkan detail mata pelajaran spesifik bagi siswa. |
| **GET** | `/api/siswa/mata-pelajaran/{id}/tugas` | `SiswaController@tugasMataPelajaran` | `auth:api`, `role:siswa` | Menampilkan daftar tugas pada mata pelajaran spesifik bagi siswa. |
| **GET** | `/api/siswa/tugas/{tugasId}` | `SiswaController@detailTugas` | `auth:api`, `role:siswa` | Menampilkan detail spesifik dari tugas tertentu bagi siswa. |

---

## 5. Rombongan Belajar (Rombel) & Anggota Kelas

| Method | Endpoint | Handler | Middleware / Akses | Fungsi |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/rombel` | `RombelController@index` | `auth:api`, `role:admin` | Menampilkan seluruh daftar rombel (Rombongan Belajar). |
| **POST** | `/api/rombel` | `RombelController@store` | `auth:api`, `role:admin` | Membuat rombel baru. |
| **GET** | `/api/rombel/form-data` | `RombelController@formData` | `auth:api`, `role:admin` | Mengambil data pendukung form konfigurasi rombel. |
| **GET** | `/api/rombel/{rombel}` | `RombelController@show` | `auth:api`, `role:admin` | Menampilkan detail data rombel spesifik beserta anggotanya. |
| **PUT/PATCH** | `/api/rombel/{rombel}` | `RombelController@update` | `auth:api`, `role:admin` | Memperbarui data rombel. |
| **DELETE** | `/api/rombel/{rombel}` | `RombelController@destroy` | `auth:api`, `role:admin` | Menghapus rombel. |
| **POST** | `/api/rombel/{id}/assign` | `RombelController@assign` | `auth:api`, `role:admin` | Memasukkan siswa ke rombel tertentu. |
| **DELETE** | `/api/rombel/{id}/kick/{siswa_id}` | `RombelController@kick` | `auth:api`, `role:admin` | Mengeluarkan siswa dari rombel tertentu. |
| **POST** | `/api/rombel/{id}/assign-mapel` | `RombelController@assignMapel` | `auth:api`, `role:admin` | Menugaskan mata pelajaran ke rombel tertentu. |
| **GET** | `/api/rombel/{id}/mapel` | `RombelController@getMapel` | `auth:api`, `role:admin` | Menampilkan daftar mata pelajaran yang dikaitkan ke rombel tertentu. |
| **POST** | `/api/rombel/promote` | `RombelController@promote` | `auth:api`, `role:admin` | Menaikkan kelas siswa di rombel terpilih. |
| **POST** | `/api/rombel/graduate` | `RombelController@graduate` | `auth:api`, `role:admin` | Meluluskan siswa tingkat akhir di rombel terpilih. |
| **GET** | `/api/anggota-kelas` | `AnggotaKelasController@index` | `auth:api`, `role:admin,guru` | Menampilkan data anggota kelas (siswa). |
| **POST** | `/api/anggota-kelas` | `AnggotaKelasController@store` | `auth:api`, `role:admin` | Memasukkan siswa ke rombel secara manual. |
| **DELETE** | `/api/anggota-kelas/{id}` | `AnggotaKelasController@destroy` | `auth:api`, `role:admin` | Mengeluarkan siswa dari rombel secara manual berdasarkan ID anggota kelas. |

---

## 6. Materi Pembelajaran (`MateriController`)

| Method | Endpoint | Handler | Middleware / Akses | Fungsi |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/guru/materi` | `MateriController@index` | `auth:api`, `role:guru` | Menampilkan semua materi pembelajaran milik guru yang login. |
| **POST** | `/api/guru/materi` | `MateriController@store` | `auth:api`, `role:guru` | Mengunggah materi pembelajaran baru. |
| **GET** | `/api/guru/materi/{materi}` | `MateriController@show` | `auth:api`, `role:guru` | Menampilkan detail materi pembelajaran tertentu. |
| **PUT/PATCH** | `/api/guru/materi/{materi}` | `MateriController@update` | `auth:api`, `role:guru` | Memperbarui materi pembelajaran tertentu. |
| **DELETE** | `/api/guru/materi/{materi}` | `MateriController@destroy` | `auth:api`, `role:guru` | Menghapus materi pembelajaran tertentu. |

---

## 7. Tugas (Assignments) & Pengumpulan (Submissions)

| Method | Endpoint | Handler | Middleware / Akses | Fungsi |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/tugas` | `TugasController@index` | `auth:api`, `role:guru` | Menampilkan seluruh daftar tugas milik guru yang login. |
| **POST** | `/api/tugas` | `TugasController@store` | `auth:api`, `role:guru` | Membuat tugas baru untuk rombel/mata pelajaran tertentu. |
| **GET** | `/api/tugas/form-data` | `TugasController@formData` | `auth:api`, `role:guru` | Mengambil data pendukung untuk form tugas (rombel/mapel). |
| **GET** | `/api/tugas/{tuga}` | `TugasController@show` | `auth:api`, `role:guru` | Menampilkan detail tugas tertentu. |
| **PUT/PATCH** | `/api/tugas/{tuga}` | `TugasController@update` | `auth:api`, `role:guru` | Memperbarui data tugas tertentu. |
| **DELETE** | `/api/tugas/{tuga}` | `TugasController@destroy` | `auth:api`, `role:guru` | Menghapus tugas tertentu. |
| **GET** | `/api/tugas/{id}/pengumpulan` | `TugasController@pengumpulanByTugas` | `auth:api`, `role:guru` | Menampilkan seluruh berkas pengumpulan siswa untuk tugas tertentu. |
| **POST** | `/api/pengumpulan/{pengumpulanId}/nilai` | `TugasController@berikanNilai` | `auth:api`, `role:guru` | Memberikan/memperbarui nilai pengumpulan tugas siswa. |
| **GET** | `/api/recap/nilai` | `RecapController@downloadRecap` | `auth:api`, `role:admin,guru` | Mengunduh/rekap nilai mata pelajaran dalam bentuk file spreadsheet. |
| **GET** | `/api/tugas-susulan` | `TugasSusulanController@index` | `auth:api`, `role:guru` | Menampilkan daftar tugas susulan yang diizinkan oleh guru. |
| **POST** | `/api/tugas-susulan` | `TugasSusulanController@store` | `auth:api`, `role:guru` | Memberikan izin pengerjaan tugas susulan ke siswa tertentu. |
| **DELETE** | `/api/tugas-susulan/{tugas_susulan}` | `TugasSusulanController@destroy` | `auth:api`, `role:guru` | Menghapus/membatalkan izin tugas susulan. |
| **GET** | `/api/siswa/tugas-susulan` | `TugasSusulanController@siswaIndex` | `auth:api`, `role:siswa` | Menampilkan rute daftar tugas susulan bagi siswa yang login. |
| **GET** | `/api/siswa/tugas-susulan/{id}` | `TugasSusulanController@siswaShow` | `auth:api`, `role:siswa` | Menampilkan detail data tugas susulan bagi siswa. |
| **POST** | `/api/pengumpulan` | `PengumpulanController@store` | `auth:api`, `role:siswa` | Mengirim/mengumpulkan berkas tugas (Siswa). |
| **DELETE** | `/api/siswa/pengumpulan/{id}` | `PengumpulanController@batal` | `auth:api`, `role:siswa` | Membatalkan/menghapus pengumpulan tugas (Siswa). |
| **PUT** | `/api/pengumpulan/{id}` | `PengumpulanController@update` | `auth:api` | Memperbarui isi berkas pengumpulan tugas yang sudah diunggah. |
| **GET** | `/api/pengumpulan` | `PengumpulanController@index` | `auth:api`, `role:admin` | Menampilkan seluruh data pengumpulan (Admin). |
| **GET** | `/api/pengumpulan/{id}` | `PengumpulanController@show` | `auth:api`, `role:admin` | Menampilkan data pengumpulan spesifik (Admin). |
| **DELETE** | `/api/pengumpulan/{id}` | `PengumpulanController@destroy` | `auth:api`, `role:admin` | Menghapus data/berkas pengumpulan tertentu (Admin). |

---

## 8. Master Data Akademik (Kelas, Mapel, Jurusan) - Admin

| Method | Endpoint | Handler | Middleware / Akses | Fungsi |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/kelas` | `KelasController@index` | `auth:api`, `role:admin` | Menampilkan seluruh tingkat/tingkatan kelas. |
| **POST** | `/api/kelas` | `KelasController@store` | `auth:api`, `role:admin` | Membuat tingkat kelas baru. |
| **GET** | `/api/kelas/{kela}` | `KelasController@show` | `auth:api`, `role:admin` | Menampilkan detail tingkat kelas tertentu. |
| **PUT/PATCH** | `/api/kelas/{kela}` | `KelasController@update` | `auth:api`, `role:admin` | Memperbarui tingkat kelas tertentu. |
| **DELETE** | `/api/kelas/{kela}` | `KelasController@destroy` | `auth:api`, `role:admin` | Menghapus tingkat kelas tertentu. |
| **GET** | `/api/mata-pelajaran` | `MataPelajaranController@index` | `auth:api`, `role:admin` | Menampilkan daftar seluruh mata pelajaran. |
| **POST** | `/api/mata-pelajaran` | `MataPelajaranController@store` | `auth:api`, `role:admin` | Membuat mata pelajaran baru. |
| **POST** | `/api/mata-pelajaran/import` | `MataPelajaranController@import` | `auth:api`, `role:admin` | Mengimpor data mata pelajaran secara massal dari file Excel/CSV. |
| **GET** | `/api/mata-pelajaran/form-data` | `MataPelajaranController@formData` | `auth:api`, `role:admin` | Mengambil data pendukung konfigurasi mata pelajaran. |
| **GET** | `/api/mata-pelajaran/{mata_pelajaran}` | `MataPelajaranController@show` | `auth:api`, `role:admin` | Menampilkan detail mata pelajaran tertentu. |
| **PUT/PATCH** | `/api/mata-pelajaran/{mata_pelajaran}` | `MataPelajaranController@update` | `auth:api`, `role:admin` | Memperbarui mata pelajaran tertentu. |
| **DELETE** | `/api/mata-pelajaran/{mata_pelajaran}` | `MataPelajaranController@destroy` | `auth:api`, `role:admin` | Menghapus mata pelajaran tertentu. |
| **GET** | `/api/mapel/filter` | `MataPelajaranController@filterMapel` | `auth:api`, `role:admin` | Menyaring/memfilter mata pelajaran. |
| **GET** | `/api/jurusan` | `JurusanController@index` | `auth:api`, `role:admin` | Menampilkan seluruh daftar jurusan. |
| **POST** | `/api/jurusan` | `JurusanController@store` | `auth:api`, `role:admin` | Membuat jurusan baru. |
| **GET** | `/api/jurusan/{jurusan}` | `JurusanController@show` | `auth:api`, `role:admin` | Menampilkan detail jurusan tertentu. |
| **PUT/PATCH** | `/api/jurusan/{jurusan}` | `JurusanController@update` | `auth:api`, `role:admin` | Memperbarui jurusan tertentu. |
| **DELETE** | `/api/jurusan/{jurusan}` | `JurusanController@destroy` | `auth:api`, `role:admin` | Menghapus jurusan tertentu. |

---

## 9. Komunikasi (Forum Diskusi & Pengumuman)

| Method | Endpoint | Handler | Middleware / Akses | Fungsi |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/diskusi/{mapel_id}` | `DiskusiController@index` | `auth:api` | Menampilkan riwayat pesan diskusi berdasarkan ID mata pelajaran. |
| **POST** | `/api/diskusi/{mapel_id}` | `DiskusiController@store` | `auth:api` | Mengirim/memposting pesan baru ke forum diskusi mata pelajaran. |
| **DELETE** | `/api/diskusi/{id}` | `DiskusiController@destroy` | `auth:api` | Menghapus pesan diskusi tertentu milik pengirim. |
| **GET** | `/api/pengumuman` | `PengumumanController@index` | `auth:api` | Menampilkan semua daftar pengumuman aktif. |
| **GET** | `/api/pengumuman/{pengumuman}` | `PengumumanController@show` | `auth:api` | Menampilkan detail isi pengumuman tertentu. |
| **POST** | `/api/pengumuman` | `PengumumanController@store` | `auth:api`, `role:guru` | Membuat pengumuman baru (Guru). |
| **PUT/PATCH** | `/api/pengumuman/{pengumuman}` | `PengumumanController@update` | `auth:api`, `role:guru` | Memperbarui isi pengumuman tertentu (Guru). |
| **DELETE** | `/api/pengumuman/{pengumuman}` | `PengumumanController@destroy` | `auth:api`, `role:guru` | Menghapus pengumuman tertentu (Guru). |

---

## 10. Utilitas & Broadcaster Internal

| Method | Endpoint | Handler | Middleware / Akses | Fungsi |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/users` | `UserController@index` | `auth:api`, `role:admin` | Menampilkan daftar seluruh user account. |
| **POST** | `/api/users` | `UserController@store` | `auth:api`, `role:admin` | Menambahkan user baru secara administratif. |
| **POST** | `/api/broadcasting/auth` | `BroadcastingAuthController@authorize` | `auth:api` | Endpoint otentikasi channel privat Laravel Echo / Pusher / Reverb. |
| **POST** | `/api/test-broadcast` | Closure (`api.php:151`) | `auth:api` | Rute uji coba pengiriman broadcast pengumuman secara realtime. |
| **GET** | `/api/test-reverb` | Closure (`api.php:32`) | Public | Rute uji coba event Reverb Websocket (ForumMessageSent). |
| **GET** | `/api` | Closure (`api.php:27`) | Public | Rute selamat datang/landing page API. |
