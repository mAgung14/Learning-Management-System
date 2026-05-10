<?php

namespace App\Events;

use App\Models\Pengumuman;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PengumumanCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Pengumuman $pengumuman;

    public function __construct(Pengumuman $pengumuman)
    {
        $this->pengumuman = $pengumuman;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('pengumuman');
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->pengumuman->id,
            'judul' => $this->pengumuman->judul,
            'deskripsi' => $this->pengumuman->deskripsi,
            'mapel_id' => $this->pengumuman->mapel_id,
            'anggota_kelas_id' => $this->pengumuman->anggota_kelas_id,
            'user_id' => $this->pengumuman->user_id,
            'created_at' => $this->pengumuman->created_at?->toDateTimeString(),
        ];
    }
}
