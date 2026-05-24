<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Pengumpulan;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Http\Requests\StorePengumpulanRequest;
use Illuminate\Http\Request;

class PengumpulanController extends Controller
{
    public function index()
    {
        $user = auth('api')->user();

        if ($user->role === 'siswa') {
            $siswa = Siswa::where('user_id', $user->id)->first();
            if (!$siswa) {
                return response()->json(['message' => 'Siswa tidak ditemukan'], 404);
            }
            return response()->json(['data' => Pengumpulan::where('siswa_id', $siswa->id)->with(['tugas', 'nilai', 'absensi'])->get()]);
        }

        return response()->json(['data' => Pengumpulan::with(['tugas', 'siswa', 'nilai', 'absensi'])->get()]);
    }

    /**
     * @contentType multipart/form-data
     */
    public function store(StorePengumpulanRequest $request)
    {
        $user = auth('api')->user();
        if ($user->role !== 'siswa') {
            return response()->json(['message' => 'Hanya siswa yang dapat mengunggah tugas'], 403);
        }

        $siswa = Siswa::where('user_id', $user->id)->first();
        if (!$siswa) {
            return response()->json(['message' => 'Data siswa tidak ditemukan'], 404);
        }

        $payload = $request->validated();

        $tugasId = $payload['tugas_id'] ?? $payload['tugasId'];
        $tugas = Tugas::find($tugasId);

        if (!$tugas) {
            return response()->json(['message' => 'Tugas tidak ditemukan'], 404);
        }

        // Cek Deadline
        $tugasSusulan = \App\Models\TugasSusulan::where('tugas_id', $tugasId)
            ->where('siswa_id', $siswa->id)
            ->first();

        if ($tugasSusulan) {
            if (\Carbon\Carbon::parse($tugasSusulan->deadline)->isPast()) {
                return response()->json([
                    'message' => 'Batas waktu pengumpulan tugas susulan sudah berakhir pada ' . \Carbon\Carbon::parse($tugasSusulan->deadline)->format('d M Y, H:i')
                ], 400);
            }
        } else {
            if (\Carbon\Carbon::parse($tugas->deadline)->isPast()) {
                return response()->json([
                    'message' => 'Batas waktu pengumpulan tugas sudah berakhir pada ' . \Carbon\Carbon::parse($tugas->deadline)->format('d M Y, H:i')
                ], 400);
            }
        }

        // Cek apakah sudah pernah mengumpulkan
        $pengumpulan = Pengumpulan::where('tugas_id', $tugasId)->where('siswa_id', $siswa->id)->first();

        // Upload file jika ada
        $fileUrl = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('pengumpulan_files', 'public');
            $fileUrl = asset('storage/' . $path);
        }

        $link = $request->input('link');

        if ($pengumpulan) {
            // Jika ada file baru diunggah, hapus file lama dan simpan file baru
            if ($request->hasFile('file')) {
                if ($pengumpulan->file) {
                    $oldPath = str_replace(asset('storage') . '/', '', $pengumpulan->file);
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($oldPath)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
                    }
                }
                $pengumpulan->file = $fileUrl;
            }

            // Jika input link dikirimkan (bisa string URL atau null jika dihapus), update link
            if ($request->has('link')) {
                $pengumpulan->link = $link;
            }

            $pengumpulan->submitted_at = now();
            $pengumpulan->save();
        } else {
            // Buat pengumpulan baru
            $pengumpulan = Pengumpulan::create([
                'tugas_id' => $tugasId,
                'siswa_id' => $siswa->id,
                'file' => $fileUrl,
                'link' => $link,
                'submitted_at' => now(),
            ]);
        }

        Absensi::updateOrCreate(
            ['pengumpulan_id' => $pengumpulan->id],
            [
                'siswa_id' => $siswa->id,
                'tugas_id' => $tugasId,
                'status' => 'hadir',
                'keterangan' => 'Absensi otomatis saat upload tugas',
                'kehadiran_pada' => now(),
            ]
        );

        return response()->json([
            'message' => 'Pengumpulan tugas berhasil disimpan dan absensi tercatat',
            'data' => $pengumpulan,
        ], 201);
    }

    public function show($id)
    {
        return response()->json([
            'data' => Pengumpulan::with(['tugas', 'siswa', 'nilai', 'absensi'])->findOrFail($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $pengumpulan = Pengumpulan::findOrFail($id);

        $payload = $request->validate([
            'file_url' => 'sometimes|string|max:1024',
            'fileUrl' => 'sometimes|string|max:1024',
            'dikumpulkan_pada' => 'sometimes|date',
            'status' => 'sometimes|string|max:50',
        ]);

        $data = [];
        if (isset($payload['file_url'])) {
            $data['file_url'] = $payload['file_url'];
        }
        if (isset($payload['fileUrl'])) {
            $data['file_url'] = $payload['fileUrl'];
        }
        if (isset($payload['dikumpulkan_pada'])) {
            $data['dikumpulkan_pada'] = $payload['dikumpulkan_pada'];
        }
        if (isset($payload['status'])) {
            $data['status'] = $payload['status'];
        }

        $pengumpulan->update($data);

        return response()->json([
            'message' => 'Pengumpulan berhasil diupdate',
            'data' => $pengumpulan,
        ]);
    }

    public function batal($id)
    {
        $user = auth('api')->user();

        if (!$user || $user->role !== 'siswa') {
            return response()->json(['message' => 'Hanya siswa yang dapat membatalkan pengumpulan'], 403);
        }

        $siswa = Siswa::where('user_id', $user->id)->first();
        if (!$siswa) {
            return response()->json(['message' => 'Data siswa tidak ditemukan'], 404);
        }

        $pengumpulan = Pengumpulan::find($id);

        if (!$pengumpulan) {
            return response()->json(['message' => 'Data pengumpulan tidak ditemukan'], 404);
        }

        // Pastikan ini benar-benar tugas siswa tersebut
        if ($pengumpulan->siswa_id !== $siswa->id) {
            return response()->json(['message' => 'Anda tidak berhak membatalkan pengumpulan ini'], 403);
        }

        // Hapus file fisik
        if ($pengumpulan->file) {
            $oldPath = str_replace(asset('storage') . '/', '', $pengumpulan->file);
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($oldPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
            }
        }

        // Hapus data absensi terkait agar statusnya tidak HADIR lagi
        Absensi::where('pengumpulan_id', $pengumpulan->id)->delete();
        
        // Hapus nilai jika terlanjur ada (meskipun seharusnya belum ada)
        \App\Models\Nilai::where('pengumpulan_id', $pengumpulan->id)->delete();

        $pengumpulan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengumpulan berhasil dibatalkan dan file telah dihapus'
        ]);
    }

    public function destroy($id)
    {
        $pengumpulan = Pengumpulan::find($id);
        if ($pengumpulan) {
            if ($pengumpulan->file) {
                $oldPath = str_replace(asset('storage') . '/', '', $pengumpulan->file);
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($oldPath)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
                }
            }
            $pengumpulan->delete();
        }

        return response()->json([
            'message' => 'Pengumpulan berhasil dihapus'
        ]);
    }
}
