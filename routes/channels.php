<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('pengumuman', function () {
    return true;
});

Broadcast::channel('diskusi.{mapel_id}', function ($user, $mapel_id) {
    return true; 
});

Broadcast::channel('pengumuman.mapel.{mapel_id}', function ($user, $mapel_id) {
    return true; // Autentikasi user sudah login
});
