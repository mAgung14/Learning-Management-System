<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Materi;
use App\Models\MataPelajaran;
use App\Http\Requests\StoreMateriRequest;
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
    /**
     * @contentType multipart/form-data
     * @bodyParam files file[] File-file materi (array).
     * @bodyParam file1 file File pertama.
     * @bodyParam file2 file File kedua.
     * @bodyParam file3 file File ketiga.
     */
    public function store(StoreMateriRequest $request)
    {
        $payload = $request->validated();

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

        // Kumpulkan semua file dari array 'files' maupun field individu 'file1', 'file2', 'file3'
        $allFiles = $request->file('files') ?? [];
        if (!is_array($allFiles)) $allFiles = [$allFiles];

        foreach (['file1', 'file2', 'file3'] as $fKey) {
            if ($request->hasFile($fKey)) {
                $allFiles[] = $request->file($fKey);
            }
        }

        if (count($allFiles) > 0) {
            foreach ($allFiles as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('materi_files', $filename, 'public');
                
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
    /**
     * @contentType multipart/form-data
     * @bodyParam files file[] File-file materi (array).
     * @bodyParam file1 file File pertama.
     * @bodyParam file2 file File kedua.
     * @bodyParam file3 file File ketiga.
     */
    public function update(Request $request, $id)
    {
        $materi = Materi::findOrFail($id);
        $user = auth()->user();

        // Otorisasi: Pastikan guru yang mengupdate adalah pembuat materi ini
        if ($user && $user->role === 'guru') {
            if ($materi->guru_id !== $user->guru->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Anda bukan pembuat materi ini.'
                ], 403);
            }
        }

        $payload = $request->validate([
            'judul' => 'sometimes|string|max:255',
            'deskripsi' => 'sometimes|string',
            'mapel_id' => 'sometimes|exists:mata_pelajaran,id',
            'mapelId' => 'sometimes|exists:mata_pelajaran,id',
            'guru_id' => 'sometimes|exists:guru,id',
            'guruId' => 'sometimes|exists:guru,id',
            'rombel_id' => 'sometimes|nullable|exists:rombel,id',
            'files' => 'sometimes|array',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,png,jpg,jpeg,gif,mp4,mkv,webm|max:20480',
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

        // Kumpulkan semua file baru
        $allFiles = $request->file('files') ?? [];
        if (!is_array($allFiles)) $allFiles = [$allFiles];

        foreach (['file1', 'file2', 'file3'] as $fKey) {
            if ($request->hasFile($fKey)) {
                $allFiles[] = $request->file($fKey);
            }
        }

        if (count($allFiles) > 0) {
            foreach ($allFiles as $file) {
                // Pastikan format aman (karena file1/file2/file3 divalidasi manual di sini atau validator request)
                $validator = \Validator::make(['file' => $file], [
                    'file' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,png,jpg,jpeg,gif,mp4,mkv,webm|max:20480'
                ]);
                if ($validator->fails()) {
                    continue; // Skip jika tidak valid
                }

                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('materi_files', $filename, 'public');
                
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

    public function destroy($id)
    {
        $materi = Materi::with('files')->findOrFail($id);
        $user = auth()->user();

        // Otorisasi: Pastikan guru yang menghapus adalah pembuat materi ini
        if ($user && $user->role === 'guru') {
            if ($materi->guru_id !== $user->guru->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Anda bukan pembuat materi ini.'
                ], 403);
            }
        }

        // Hapus file fisik dari storage
        foreach ($materi->files as $file) {
            if ($file->tipe !== 'YOUTUBE') {
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

