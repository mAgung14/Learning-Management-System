<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Models\Diskusi;
use App\Models\MataPelajaran;
use App\Events\MessageSent;
use App\Events\MessageDeleted;

class DiskusiController extends Controller
{
    /**
     * Get chat messages for a specific mapel.
     * 
     * @tags Diskusi Mapel
     * @response array{status: string, data: list<Diskusi>}
     */
    public function index($mapel_id)
    {
        $mapel = MataPelajaran::findOrFail($mapel_id);
        
        $messages = Diskusi::with(['user.siswa', 'user.guru'])
            ->where('mata_pelajaran_id', $mapel->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                return $this->formatMessage($msg);
            });

        return response()->json([
            'status' => 'success',
            'data' => $messages
        ]);
    }

    /**
     * Store a new chat message and broadcast it.
     * 
     * @tags Diskusi Mapel
     */
    public function store(Request $request, $mapel_id)
    {
        $request->validate([
            'pesan' => 'required|string',
        ]);

        $mapel = MataPelajaran::findOrFail($mapel_id);

        $diskusi = Diskusi::create([
            'mata_pelajaran_id' => $mapel->id,
            'user_id' => auth()->id(),
            'pesan' => $request->pesan,
        ]);

        $diskusi->load(['user.siswa', 'user.guru']);

        // Broadcast the event (best-effort, jangan sampai gagal broadcast merusak response)
        try {
            broadcast(new MessageSent($diskusi))->toOthers();
        } catch (\Exception $e) {
            Log::warning('Broadcast diskusi gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pesan berhasil dikirim',
            'data' => $this->formatMessage($diskusi)
        ], 201);
    }

    /**
     * Format pesan diskusi dengan nama pengirim berdasarkan role.
     */
    private function formatMessage(Diskusi $diskusi): array
    {
        $user = $diskusi->user;
        
        // Tentukan nama pengirim berdasarkan role
        if ($user->role === 'guru' && $user->guru) {
            $namaPengirim = $user->guru->nama;
        } elseif ($user->role === 'siswa' && $user->siswa) {
            $namaPengirim = $user->siswa->nama;
        } else {
            $namaPengirim = $user->name; // fallback (admin, dll)
        }

        return [
            'id' => $diskusi->id,
            'mata_pelajaran_id' => $diskusi->mata_pelajaran_id,
            'user_id' => $diskusi->user_id,
            'pesan' => $diskusi->pesan,
            'nama_pengirim' => $namaPengirim,
            'role' => $user->role,
            'created_at' => $diskusi->created_at,
            'updated_at' => $diskusi->updated_at,
        ];
    }

    /**
     * Delete a chat message.
     * 
     * @tags Diskusi Mapel
     */
    public function destroy($id)
    {
        $diskusi = Diskusi::findOrFail($id);
        $user = auth()->user();

        // Diizinkan menghapus jika:
        // 1. User adalah pembuat pesan tersebut
        // 2. User adalah guru
        if ($diskusi->user_id !== $user->id && $user->role !== 'guru') {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses untuk menghapus pesan ini'
            ], 403);
        }

        $mapelId = $diskusi->mata_pelajaran_id;

        $diskusi->delete();

        // Broadcast event
        try {
            broadcast(new MessageDeleted($id, $mapelId))->toOthers();
        } catch (\Exception $e) {
            Log::warning('Broadcast hapus diskusi gagal: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pesan berhasil dihapus'
        ]);
    }
}
