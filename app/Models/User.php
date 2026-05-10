<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\Anggota_kelas;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function anggotaKelas()
    {
        // Parameter kedua adalah nama foreign key di tabel anggota_kelas
        // Parameter ketiga adalah nama primary key di tabel users
        return $this->hasMany(Anggota_kelas::class, 'userId', 'id');
    }

    public function kelas()
    {
        return $this->belongsToMany(Kelas::class, 'anggota_kelas', 'userId', 'kelasId')
                    ->withPivot('peran') // Membawa kolom 'peran' agar bisa dibaca
                    ->withTimestamps();
    }

    public function siswa()
{
    return $this->hasOne(Siswa::class);
}

public function guru()
{
    return $this->hasOne(Guru::class);
}

public function pengumuman()
{
    return $this->hasMany(Pengumuman::class, 'user_id');
}
    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
