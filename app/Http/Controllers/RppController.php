<?php

namespace App\Http\Controllers;

use App\Models\Rpp;
use App\Http\Requests\StoreRppRequest;
use App\Http\Requests\UpdateRppRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RppController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $query = Rpp::with(['mapel', 'guru', 'files']);

        // Jika user adalah guru, hanya tampilkan RPP milik dia
        if ($user && $user->role === 'guru') {
            $query->where('guru_id', $user->guru->id);
        }

        // Filter by mapel_id jika ada
        if ($request->has('mapel_id')) {
            $query->where('mapel_id', $request->mapel_id);
        }

        $rpps = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $rpps
        ]);
    }

    public function store(StoreRppRequest $request)
    {
        $payload = $request->validated();
        $user = auth()->user();
        
        $guru_id = $request->guru_id ?? null;
        if ($user && $user->role === 'guru') {
            $guru_id = $user->guru->id;
        }

        if (!$guru_id) {
            return response()->json([
                'success' => false,
                'message' => 'guru_id is required'
            ], 400);
        }

        $rpp = Rpp::create([
            'guru_id' => $guru_id,
            'mapel_id' => $payload['mapel_id'],
            'judul' => $payload['judul'],
            'deskripsi' => $payload['deskripsi'] ?? null,
        ]);

        $allFiles = $request->file('files') ?? [];
        if (!is_array($allFiles)) {
            $allFiles = [$allFiles];
        }

        foreach ($allFiles as $file) {
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('rpp_files', $filename, 'public');
            
            $mimeType = $file->getClientMimeType();
            $tipe = 'FILE';
            if (str_starts_with($mimeType, 'application/pdf')) {
                $tipe = 'PDF';
            }

            $rpp->files()->create([
                'nama_file' => $file->getClientOriginalName(),
                'tipe' => $tipe,
                'url' => asset('storage/' . $path),
            ]);
        }

        $rpp->load('files');

        return response()->json([
            'success' => true,
            'message' => 'RPP berhasil ditambahkan',
            'data' => $rpp
        ], 201);
    }

    public function show($id)
    {
        $rpp = Rpp::with(['mapel', 'guru', 'files'])->findOrFail($id);
        
        $user = auth()->user();
        if ($user && $user->role === 'guru' && $rpp->guru_id !== $user->guru->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda bukan pemilik RPP ini.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $rpp
        ]);
    }

    public function update(UpdateRppRequest $request, $id)
    {
        $rpp = Rpp::findOrFail($id);
        $user = auth()->user();

        if ($user && $user->role === 'guru' && $rpp->guru_id !== $user->guru->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda bukan pemilik RPP ini.'
            ], 403);
        }

        $payload = $request->validated();
        
        $data = [];
        if (isset($payload['judul'])) $data['judul'] = $payload['judul'];
        if (array_key_exists('deskripsi', $payload)) $data['deskripsi'] = $payload['deskripsi'];
        if (isset($payload['mapel_id'])) $data['mapel_id'] = $payload['mapel_id'];

        $rpp->update($data);

        $allFiles = $request->file('files') ?? [];
        if (!is_array($allFiles)) {
            $allFiles = [$allFiles];
        }

        if (count($allFiles) > 0) {
            foreach ($allFiles as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('rpp_files', $filename, 'public');
                
                $mimeType = $file->getClientMimeType();
                $tipe = 'FILE';
                if (str_starts_with($mimeType, 'application/pdf')) {
                    $tipe = 'PDF';
                }

                $rpp->files()->create([
                    'nama_file' => $file->getClientOriginalName(),
                    'tipe' => $tipe,
                    'url' => asset('storage/' . $path),
                ]);
            }
        }

        $rpp->load('files');

        return response()->json([
            'success' => true,
            'message' => 'RPP berhasil diupdate',
            'data' => $rpp
        ]);
    }

    public function destroy($id)
    {
        $rpp = Rpp::with('files')->findOrFail($id);
        $user = auth()->user();

        if ($user && $user->role === 'guru' && $rpp->guru_id !== $user->guru->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda bukan pemilik RPP ini.'
            ], 403);
        }

        foreach ($rpp->files as $file) {
            $relativePath = str_replace(asset('storage') . '/', '', $file->url);
            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
        }

        $rpp->delete();

        return response()->json([
            'success' => true,
            'message' => 'RPP berhasil dihapus'
        ]);
    }
}
