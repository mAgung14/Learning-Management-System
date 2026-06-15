<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guru extends Model
{
    protected $table = 'guru';
    protected $fillable = ['user_id', 'nik', 'nama', 'jenis_kelamin'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::deleting(function (Guru $guru) {
            $user = $guru->user;

            // Hanya hapus akun user jika ini akun guru murni.
            // Jangan hapus jika akun tersebut juga terkait sebagai siswa.
            if ($user && $user->role === 'guru' && !$user->siswa) {
                $user->delete();
            }
        });
    }

    public function mapel()
    {
        return $this->belongsToMany(MataPelajaran::class, 'guru_mapel');
    }
}
