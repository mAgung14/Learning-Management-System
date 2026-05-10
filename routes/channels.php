<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('pengumuman', function () {
    return true;
});
