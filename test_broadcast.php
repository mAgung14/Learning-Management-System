<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    broadcast(new App\Events\MessageSent(App\Models\Diskusi::first() ?? new App\Models\Diskusi(['mata_pelajaran_id' => 1])));
    echo 'SUCCESS';
} catch (\Exception $e) {
    echo $e->getMessage();
}
