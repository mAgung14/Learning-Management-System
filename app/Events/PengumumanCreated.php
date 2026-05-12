<?php

namespace App\Events;

use App\Models\Pengumuman;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
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

    public function broadcastOn(): array
    {
        $channels = [new Channel('pengumuman')];

        if ($this->pengumuman->mapel_id) {
            $channels[] = new Channel('pengumuman.mapel.' . $this->pengumuman->mapel_id);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        $this->pengumuman->load(['user:id,username', 'mapel:id,nama_mapel']);
        
        return [
            'id' => $this->pengumuman->id,
            'judul' => $this->pengumuman->judul,
            'deskripsi' => $this->pengumuman->deskripsi,
            'mapel_id' => $this->pengumuman->mapel_id,
            'nama_mapel' => $this->pengumuman->mapel?->nama_mapel,
            'pengirim' => $this->pengumuman->user?->username,
            'created_at' => $this->pengumuman->created_at?->diffForHumans(),
        ];
    }
}
