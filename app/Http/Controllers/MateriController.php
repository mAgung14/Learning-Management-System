<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Materi;
use App\Models\MataPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MateriController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Materi::with(['mapel', 'guru', 'rombel', 'files'])->get()
        ]);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'judul' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'mapel_id' => 'required|exists:mata_pelajaran,id',
            'mapelId' => 'sometimes|exists:mata_pelajaran,id',
            'guru_id' => 'sometimes|exists:guru,id',
            'guruId' => 'sometimes|exists:guru,id',
            'rombel_id' => 'sometimes|nullable|exists:rombel,id',
            'files.*' => 'sometimes|file|max:20480' ?? null,
            'youtube_urls.*' => 'sometimes|url' ?? null,
            'youtube_urls' => 'sometimes' ?? null,
            'youtube_url' => 'sometimes|url' ?? null,
            'link_youtube' => 'sometimes|url' ?? null,
        ]);

        $user = auth('api')->user();
        $guru_id = $payload['guru_id'] ?? $payload['guruId'] ?? null;
        if ($user && $user->role === 'guru') {
            $guru_id = $user->guru->id;
        }

        if (!$guru_id) {
            return response()->json(['message' => 'guru_id is required'], 400);
        }

        $data = [
            'judul' => $payload['judul'],
            'deskripsi' => $payload['deskripsi'],
            'mapel_id' => $payload['mapel_id'] ?? $payload['mapelId'],
            'guru_id' => $guru_id,
            'rombel_id' => $payload['rombel_id'] ?? null,
        ];

        $materi = Materi::create($data);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('materi_files', 'public');
                
                $mimeType = $file->getClientMimeType();
                $tipe = 'FILE';
                if (str_starts_with($mimeType, 'image/')) {
                    $tipe = 'IMAGE';
                } elseif (str_starts_with($mimeType, 'video/')) {
                    $tipe = 'VIDEO';
                } elseif ($mimeType === 'application/pdf') {
                    $tipe = 'PDF';
                }

                $materi->files()->create([
                    'tipe' => $tipe,
                    'url' => asset('storage/' . $path),
                    'nama_file' => $file->getClientOriginalName(),
                ]);
            }
        }

        $ytInput = $request->input('youtube_urls') ?? $request->input('youtube_url') ?? $request->input('link_youtube') ?? $request->input('youtube');
        if ($ytInput) {
            $urls = $ytInput;
            if (!is_array($urls)) {
                $urls = [$urls]; // Jadikan array jika hanya 1 link
            }
            foreach ($urls as $url) {
                if (!empty($url)) {
                    // Konversi URL biasa menjadi URL embed
                    $embedUrl = $url;
                    if (str_contains($url, 'watch?v=')) {
                        $embedUrl = str_replace('watch?v=', 'embed/', $url);
                        $embedUrl = explode('&', $embedUrl)[0]; // buang parameter lain
                    } elseif (str_contains($url, 'youtu.be/')) {
                        $embedUrl = str_replace('youtu.be/', 'www.youtube.com/embed/', $url);
                        $embedUrl = explode('?', $embedUrl)[0]; // buang parameter lain
                    }

                    $materi->files()->create([
                        'tipe' => 'YOUTUBE',
                        'url' => $embedUrl,
                        'nama_file' => 'Video YouTube',
                    ]);
                }
            }
        }

        $materi->load('files');

        return response()->json([
            'message' => 'Materi berhasil dibuat',
            'data' => $materi,
        ], 201);
    }

    public function show($id)
    {
        return response()->json([
            'data' => Materi::with(['mapel', 'guru', 'rombel', 'files'])->findOrFail($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $materi = Materi::findOrFail($id);
        $payload = $request->validate([
            'judul' => 'sometimes|string|max:255',
            'deskripsi' => 'sometimes|string',
            'mapel_id' => 'sometimes|exists:mata_pelajaran,id',
            'mapelId' => 'sometimes|exists:mata_pelajaran,id',
            'guru_id' => 'sometimes|exists:guru,id',
            'guruId' => 'sometimes|exists:guru,id',
            'rombel_id' => 'sometimes|nullable|exists:rombel,id',
            'files.*' => 'sometimes|file|max:20480',
            'youtube_urls.*' => 'sometimes|url',
            'youtube_urls' => 'sometimes',
        ]);

        $data = [];
        if (isset($payload['judul'])) {
            $data['judul'] = $payload['judul'];
        }
        if (isset($payload['deskripsi'])) {
            $data['deskripsi'] = $payload['deskripsi'];
        }
        if (isset($payload['mapel_id'])) {
            $data['mapel_id'] = $payload['mapel_id'];
        }
        if (isset($payload['mapelId'])) {
            $data['mapel_id'] = $payload['mapelId'];
        }
        if (isset($payload['guru_id'])) {
            $data['guru_id'] = $payload['guru_id'];
        }
        if (isset($payload['guruId'])) {
            $data['guru_id'] = $payload['guruId'];
        }
        if (array_key_exists('rombel_id', $payload)) {
            $data['rombel_id'] = $payload['rombel_id'];
        }

        $materi->update($data);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('materi_files', 'public');
                
                $mimeType = $file->getClientMimeType();
                $tipe = 'FILE';
                if (str_starts_with($mimeType, 'image/')) {
                    $tipe = 'IMAGE';
                } elseif (str_starts_with($mimeType, 'video/')) {
                    $tipe = 'VIDEO';
                } elseif ($mimeType === 'application/pdf') {
                    $tipe = 'PDF';
                }

                $materi->files()->create([
                    'tipe' => $tipe,
                    'url' => asset('storage/' . $path),
                    'nama_file' => $file->getClientOriginalName(),
                ]);
            }
        $ytInput = $request->input('youtube_urls') ?? $request->input('youtube_url') ?? $request->input('link_youtube') ?? $request->input('youtube');
        if ($ytInput) {
            $urls = $ytInput;
            if (!is_array($urls)) {
                $urls = [$urls];
            }
            foreach ($urls as $url) {
                if (!empty($url)) {
                    // Konversi URL biasa menjadi URL embed
                    $embedUrl = $url;
                    if (str_contains($url, 'watch?v=')) {
                        $embedUrl = str_replace('watch?v=', 'embed/', $url);
                        $embedUrl = explode('&', $embedUrl)[0]; // buang parameter lain
                    } elseif (str_contains($url, 'youtu.be/')) {
                        $embedUrl = str_replace('youtu.be/', 'www.youtube.com/embed/', $url);
                        $embedUrl = explode('?', $embedUrl)[0]; // buang parameter lain
                    }

                    $materi->files()->create([
                        'tipe' => 'YOUTUBE',
                        'url' => $embedUrl,
                        'nama_file' => 'Video YouTube',
                    ]);
                }
            }
        }

        $materi->load('files');

        return response()->json([
            'message' => 'Materi berhasil diupdate',
            'data' => $materi,
        ]);
    }
}
    public function destroy($id)
    {
        $materi = Materi::with('files')->findOrFail($id);

        // Hapus file fisik dari storage
        foreach ($materi->files as $file) {
            if ($file->tipe !== 'YOUTUBE') {
                // Ekstrak path relatif dari URL, misalnya: http://localhost:8000/storage/materi_files/namafile.pdf -> materi_files/namafile.pdf
                // Perlu diperhatikan url asset('storage/' . $path)
                $relativePath = str_replace(asset('storage') . '/', '', $file->url);
                
                if (Storage::disk('public')->exists($relativePath)) {
                    Storage::disk('public')->delete($relativePath);
                }
            }
        }

        $materi->delete();

        return response()->json([
            'message' => 'Materi dan file terkait berhasil dihapus'
        ]);
    }
}
