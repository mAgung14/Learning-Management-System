<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use App\Models\Diskusi;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $diskusi;

    /**
     * Create a new event instance.
     */
    public function __construct(Diskusi $diskusi)
    {
        $this->diskusi = $diskusi;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('diskusi.' . $this->diskusi->mata_pelajaran_id),
        ];
    }
    
    public function broadcastWith(): array
    {
        $this->diskusi->load(['user.siswa', 'user.guru']);
        $user = $this->diskusi->user;

        // Tentukan nama pengirim berdasarkan role
        if ($user->role === 'guru' && $user->guru) {
            $namaPengirim = $user->guru->nama;
        } elseif ($user->role === 'siswa' && $user->siswa) {
            $namaPengirim = $user->siswa->nama;
        } else {
            $namaPengirim = $user->name;
        }

        return [
            'diskusi' => [
                'id' => $this->diskusi->id,
                'mata_pelajaran_id' => $this->diskusi->mata_pelajaran_id,
                'user_id' => $this->diskusi->user_id,
                'pesan' => $this->diskusi->pesan,
                'nama_pengirim' => $namaPengirim,
                'role' => $user->role,
                'created_at' => $this->diskusi->created_at,
                'updated_at' => $this->diskusi->updated_at,
            ]
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
