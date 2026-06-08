# LMS-KP Backend API (Learning Management System dengan Integrasi AI)

[![Laravel Version](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![AI Integration](https://img.shields.io/badge/AI-OpenAI--GPT--4o--mini-orange.svg)](https://openai.com)
[![WebSockets](https://img.shields.io/badge/Realtime-Laravel%20Reverb-purple.svg)](https://laravel.com/docs/11.x/reverb)

**LMS-KP Backend API** adalah platform backend untuk Learning Management System (LMS) modern yang dirancang untuk mendukung sistem pembelajaran sekolah terpadu. Project ini dibangun menggunakan **Laravel 13.5.0** dan dilengkapi dengan fitur otomatisasi berbasis **Kecerdasan Buatan (OpenAI)** untuk mendukung pembuatan soal (Bank Soal) dan penulisan deskripsi tugas secara efisien, serta didukung oleh fitur realtime menggunakan **Laravel Reverb**.

---

## 🚀 Fitur Utama

### 1. Sistem Autentikasi & Multi-Role (JWT-based)
*   Menggunakan **JWT (JSON Web Token)** untuk pertukaran data yang aman dan stateless.
*   Pembagian hak akses berbasis role:
    *   **Admin**: Mengelola data dasar akademik, manajemen rombel, penugasan guru, serta reset password siswa.
    *   **Guru**: Mengunggah materi, membuat tugas kelas, melakukan penilaian, dan memicu AI Generator.
    *   **Siswa**: Mengakses materi, mengunduh penugasan, mengirimkan tugas, serta berpartisipasi dalam diskusi kelas.

### 2. Manajemen Rombel & Kenaikan Kelas Otomatis
*   **Mekanisme Kenaikan Kelas (Class Promotion)**: Memindahkan siswa antar Rombongan Belajar (Rombel) secara massal (misal: X -> XI -> XII) dan menyinkronkan jurusan siswa secara otomatis. Bersamaan dengan proses ini, sistem **secara otomatis membersihkan konten kelas asal** (menghapus materi, tugas, diskusi/obrolan, pengumuman, serta file fisiknya di storage) untuk menyambut angkatan berikutnya, namun **tetap mempertahankan mata pelajaran** dan penugasan guru yang melekat pada rombel tersebut.
*   **Kelulusan Massal (Graduation)**: Memproses kelulusan siswa kelas XII secara massal dengan opsi **hapus akun secara bersih** (`delete`) atau **lepas dari rombel aktif** untuk dijadikan alumni (`detach`). Proses ini juga **secara otomatis membersihkan konten kelas lama** (materi, tugas, diskusi/obrolan, pengumuman, serta file fisiknya di storage) dengan tetap mempertahankan daftar mata pelajaran dan gurunya.

### 3. Manajemen Tugas & Pengumpulan Fleksibel
*   Siswa dapat mengumpulkan tugas dalam bentuk **file fisik (dokumen/gambar)** atau melampirkan **link eksternal (Google Drive, Figma, GitHub, dll.)**.
*   **Sistem Absensi Otomatis**: Secara otomatis mencatat kehadiran siswa dengan status `HADIR` saat mereka berhasil mengumpulkan tugas sebelum batas waktu (deadline).
*   Manajemen file yang bersih: Menghapus file fisik lama di penyimpanan secara otomatis jika siswa memperbarui tugas dengan metode baru (mengganti file menjadi link).

### 4. Integrasi Kecerdasan Buatan (AI Integration)
*   **AI-Powered Bank Soal**: Guru dapat memicu pembuatan soal evaluasi otomatis berdasarkan modul materi tertentu secara asynchronous melalui sistem **Laravel Queue Jobs** menggunakan OpenAI `gpt-4o-mini`.
*   **Synchronous AI Description Generator**: Membantu guru membuat instruksi/deskripsi tugas yang terstruktur secara langsung hanya dengan mengetikkan perintah singkat.

### 5. Fitur Komunikasi Realtime
*   **Diskusi Kelas**: Chat room interaktif realtime per mata pelajaran untuk memfasilitasi tanya jawab antara siswa dan guru.
*   **Pengumuman Realtime**: Fitur broadcast pengumuman secara instan ke seluruh pengguna aktif menggunakan **Laravel Reverb (WebSockets)**.

### 6. Impor Data Massal
*   Mendukung penginputan data guru dan siswa dalam skala besar secara praktis melalui import file CSV.

---

## 🛠️ Tech Stack & Library

*   **Framework Utama**: Laravel 13.5.0 (PHP 8.2+)
*   **Autentikasi**: Tymon JWT Auth (`tymon/jwt-auth`)
*   **Realtime/WebSockets**: Laravel Reverb
*   **AI SDK**: OpenAI API Client
*   **Database**: MySQL
*   **Queue Driver**: Database Queue Worker

---

## 📂 Struktur Database Utama

Proyek ini memiliki relasi database yang terstruktur guna mendukung proses pembelajaran:
*   `users`: Menyimpan informasi login dan role pengguna.
*   `siswa` & `guru`: Menyimpan data profil personal yang terhubung dengan tabel user.
*   `kelas`: Berisi informasi tingkatan kelas (X, XI, XII) dan tahun ajaran.
*   `rombel` & `anggota_kelas`: Menghubungkan siswa dengan kelompok belajar dan tahun ajaran yang sedang aktif.
*   `mata_pelajaran`: Menyimpan data pelajaran yang diampu oleh guru dan diikuti oleh rombel terkait.
*   `tugas` & `pengumpulan`: Mencatat data tugas dan melacak pengumpulan tugas (file/link) serta penilaian siswa.
*   `ai_generate_logs`: Melacak riwayat antrean pembuatan bank soal otomatis oleh kecerdasan buatan.

---

## ⚙️ Instalasi & Konfigurasi Lokal

Ikuti langkah-langkah berikut untuk menjalankan project ini di komputer lokal Anda:

### Prasyarat
*   PHP >= 8.2 (Pastikan extension `pdo`, `mbstring`, `openssl` aktif)
*   Composer
*   MySQL / MariaDB
*   Laragon / XAMPP (Opsional)

### Langkah-langkah
1.  **Clone Repository**
    ```bash
    git clone https://github.com/username/LMS-KP.git
    cd LMS-KP
    ```

2.  **Install Dependencies**
    ```bash
    composer install
    ```

3.  **Salin File Environment**
    ```bash
    cp .env.example .env
    ```

4.  **Konfigurasi Database & Environment Utama**
    Buka file `.env` lalu sesuaikan kredensial database Anda:
    ```env
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=nama_database_anda
    DB_USERNAME=root
    DB_PASSWORD=password_anda
    ```

    Tambahkan konfigurasi kunci OpenAI untuk menggunakan fitur AI:
    ```env
    OPENAI_API_KEY=sk-proj-xxxxxx...
    OPENAI_MODEL=gpt-4o-mini
    ```

    Pastikan driver Queue diset ke `database` agar pemrosesan AI berjalan di latar belakang:
    ```env
    QUEUE_CONNECTION=database
    ```

5.  **Generate Application Key & JWT Secret**
    ```bash
    php artisan key:generate
    php artisan jwt:secret
    ```

6.  **Jalankan Database Migration & Seeder**
    ```bash
    php artisan migrate --seed
    ```

7.  **Jalankan WebSocket Server (Reverb) & Queue Worker**
    Buka terminal baru lalu jalankan server realtime:
    ```bash
    php artisan reverb:start
    ```
    Dan jalankan queue worker untuk menangani generator soal di background:
    ```bash
    php artisan queue:work
    ```

8.  **Jalankan Local Development Server**
    ```bash
    php artisan serve
    ```
    Aplikasi Anda sekarang dapat diakses secara default di `http://127.0.0.1:8000`.

---

## 📑 API Endpoint Highlights

Berikut adalah ringkasan beberapa endpoint penting yang tersedia:

### Autentikasi (`/api`)
*   `POST /login` - Login pengguna untuk mendapatkan JWT Token.
*   `GET /me` - Mendapatkan informasi profil user saat ini.

### Modul Admin (`/api`)
*   `POST /siswa/import` - Mengimpor data siswa massal via CSV.
*   `POST /siswa/{id}/reset-password` - Reset password siswa ke default `"12345678"`.
*   `POST /rombel/promote` - Kenaikan kelas massal dari rombel asal ke rombel tujuan.
*   `POST /rombel/graduate` - Kelulusan massal dengan opsi hapus (`delete`) atau lepas dari rombel (`detach`).

### Modul Guru (`/api`)
*   `POST /bank-soal/generate` - Memicu pembuatan soal berbasis AI (Asynchronous Queue).
*   `POST /ai/generate-deskripsi` - Membuat deskripsi/instruksi tugas instan berbasis AI.
*   `POST /pengumpulan/{pengumpulanId}/nilai` - Memberikan nilai pada tugas siswa.

### Modul Siswa (`/api`)
*   `POST /pengumpulan` - Mengirimkan tugas kelas (menerima input berkas `file` atau `link`).
*   `DELETE /siswa/pengumpulan/{id}` - Membatalkan pengumpulan tugas (otomatis menghapus file fisik di storage).

---

## 📄 Lisensi

Project ini dilisensikan di bawah **[Lisensi MIT](LICENSE)** - Anda bebas menggunakan dan memodifikasi program ini untuk keperluan belajar maupun portofolio profesional.