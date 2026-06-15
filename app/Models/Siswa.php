<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Siswa extends Model
{
    protected $table = 'siswa';
    protected $fillable = ['nis','nama','jenis_kelamin','user_id','jurusan_id'];
    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id');
    }

    public function anggotaKelas()
    {
        return $this->hasMany(AnggotaKelas::class, 'siswa_id');
    }

    public function rombel()
    {
        return $this->belongsToMany(Rombel::class, 'anggota_kelas', 'siswa_id', 'rombel_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function booted()
    {
        static::deleting(function (Siswa $siswa) {
            $user = $siswa->user;

            // Hanya hapus akun user jika ini akun siswa murni.
            // Jangan hapus jika akun tersebut juga terkait sebagai guru.
            if ($user && $user->role === 'siswa' && !$user->guru) {
                $user->delete();
            }
        });
    }

    public function tugasSusulan()
    {
        return $this->hasMany(TugasSusulan::class, 'siswa_id');
    }
}
