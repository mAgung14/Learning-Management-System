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
        
        $query = Rpp::with(['mapel', 'guru', 'rombel', 'files', 'pertemuans']);

        // Jika user adalah guru, hanya tampilkan RPP milik dia
        if ($user && $user->role === 'guru') {
            $query->where('guru_id', $user->guru->id);
        }

        // Filter by mapel_id jika ada
        if ($request->has('mapel_id')) {
            $query->where('mapel_id', $request->mapel_id);
        }

        // Filter by rombel_id jika ada
        if ($request->has('rombel_id')) {
            $query->where('rombel_id', $request->rombel_id);
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

        $mapel_id = $payload['mapel_id'] ?? $payload['mapelId'] ?? null;
        $rombel_id = $payload['rombel_id'] ?? $payload['rombelId'] ?? null;
        
        $status = $payload['status'] ?? 'draft';
        if (isset($payload['is_published']) && filter_var($payload['is_published'], FILTER_VALIDATE_BOOLEAN)) {
            $status = 'approved';
        }

        $rpp = Rpp::create([
            'guru_id' => $guru_id,
            'mapel_id' => $mapel_id,
            'rombel_id' => $rombel_id,
            'judul' => $payload['judul'] ?? null,
            'deskripsi' => $payload['deskripsi'] ?? null,
            'kompetensi_dasar' => $payload['kompetensi_dasar'] ?? null,
            'indikator' => $payload['indikator'] ?? null,
            'tujuan_pembelajaran' => $payload['tujuan_pembelajaran'] ?? null,
            'status' => $status,
        ]);

        // Process pertemuans
        if (isset($payload['pertemuans'])) {
            $pertemuans = json_decode($payload['pertemuans'], true);
            if (is_array($pertemuans)) {
                foreach ($pertemuans as $p) {
                    $rpp->pertemuans()->create([
                        'pertemuan_ke' => $p['pertemuan_ke'] ?? null,
                        'topik' => $p['topik'] ?? null,
                        'kegiatan_pendahuluan' => $p['kegiatan_pendahuluan'] ?? null,
                        'kegiatan_inti' => $p['kegiatan_inti'] ?? null,
                        'kegiatan_penutup' => $p['kegiatan_penutup'] ?? null,
                        'alokasi_waktu' => $p['alokasi_waktu'] ?? null,
                    ]);
                }
            }
        }

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

        $rpp->load(['files', 'pertemuans']);

        return response()->json([
            'success' => true,
            'message' => 'RPP berhasil ditambahkan',
            'data' => $rpp
        ], 201);
    }

    public function show($id)
    {
        $rpp = Rpp::with(['mapel', 'guru', 'rombel', 'files', 'pertemuans'])->findOrFail($id);
        
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
        if (array_key_exists('judul', $payload)) $data['judul'] = $payload['judul'];
        if (array_key_exists('deskripsi', $payload)) $data['deskripsi'] = $payload['deskripsi'];
        if (array_key_exists('kompetensi_dasar', $payload)) $data['kompetensi_dasar'] = $payload['kompetensi_dasar'];
        if (array_key_exists('indikator', $payload)) $data['indikator'] = $payload['indikator'];
        if (array_key_exists('tujuan_pembelajaran', $payload)) $data['tujuan_pembelajaran'] = $payload['tujuan_pembelajaran'];
        if (isset($payload['mapel_id'])) $data['mapel_id'] = $payload['mapel_id'];
        elseif (isset($payload['mapelId'])) $data['mapel_id'] = $payload['mapelId'];
        
        if (isset($payload['rombel_id'])) $data['rombel_id'] = $payload['rombel_id'];
        elseif (isset($payload['rombelId'])) $data['rombel_id'] = $payload['rombelId'];
        
        if (isset($payload['status'])) {
            $data['status'] = $payload['status'];
        } elseif (isset($payload['is_published'])) {
            $data['status'] = filter_var($payload['is_published'], FILTER_VALIDATE_BOOLEAN) ? 'approved' : 'draft';
        }

        $rpp->update($data);

        // Process pertemuans updates (replace all for simplicity, or sync)
        if (isset($payload['pertemuans'])) {
            $pertemuans = json_decode($payload['pertemuans'], true);
            if (is_array($pertemuans)) {
                // Delete existing and recreate
                $rpp->pertemuans()->delete();
                foreach ($pertemuans as $p) {
                    $rpp->pertemuans()->create([
                        'pertemuan_ke' => $p['pertemuan_ke'] ?? null,
                        'topik' => $p['topik'] ?? null,
                        'kegiatan_pendahuluan' => $p['kegiatan_pendahuluan'] ?? null,
                        'kegiatan_inti' => $p['kegiatan_inti'] ?? null,
                        'kegiatan_penutup' => $p['kegiatan_penutup'] ?? null,
                        'alokasi_waktu' => $p['alokasi_waktu'] ?? null,
                    ]);
                }
            }
        }

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

        $rpp->load(['files', 'pertemuans']);

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

    public function updateStatus(Request $request, $id)
    {
        $rpp = Rpp::findOrFail($id);
        
        $request->validate([
            'status' => 'required|in:draft,submitted,approved'
        ]);

        $rpp->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status RPP berhasil diperbarui',
            'data' => $rpp
        ]);
    }
}
