<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('pengumuman', function () {
    return true;
});

Broadcast::channel('diskusi.{mapel_id}', function ($user, $mapel_id) {
    return true; 
});

Broadcast::channel('pengumuman.mapel.{mapel_id}', function ($user, $mapel_id) {
    // Jika guru, cek apakah dia mengajar mapel ini
    if ($user->role === 'guru') {
        return \DB::table('guru_mapel')
            ->where('guru_id', $user->guru?->id)
            ->where('mata_pelajaran_id', $mapel_id)
            ->exists();
    }

    // Jika siswa, cek apakah rombelnya memiliki mapel ini
    if ($user->role === 'siswa') {
        $rombelIds = $user->siswa?->anggotaKelas()->pluck('rombel_id')->toArray() ?? [];
        return \DB::table('rombel_mapel')
            ->whereIn('rombel_id', $rombelIds)
            ->where('mata_pelajaran_id', $mapel_id)
            ->exists();
    }

    return false;
});
