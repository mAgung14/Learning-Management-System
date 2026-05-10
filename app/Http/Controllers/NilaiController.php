<?php

namespace App\Http\Controllers;

use App\Models\Nilai;
use App\Models\Pengumpulan;
use App\Models\Siswa;
use Illuminate\Http\Request;

class NilaiController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Nilai::with(['pengumpulan', 'siswa'])->get()
        ]);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'pengumpulan_id' => 'required|exists:pengumpulan,id',
            'pengumpulanId' => 'sometimes|exists:pengumpulan,id',
            'siswa_id' => 'required|exists:siswa,id',
            'siswaId' => 'sometimes|exists:siswa,id',
            'score' => 'required|numeric|min:0',
            'catatan' => 'nullable|string',
        ]);

        $nilai = Nilai::create([
            'pengumpulan_id' => $payload['pengumpulan_id'] ?? $payload['pengumpulanId'],
            'siswa_id' => $payload['siswa_id'] ?? $payload['siswaId'],
            'score' => $payload['score'],
            'catatan' => $payload['catatan'] ?? null,
        ]);

        return response()->json([
            'message' => 'Nilai berhasil dibuat',
            'data' => $nilai,
        ], 201);
    }

    public function show($id)
    {
        return response()->json([
            'data' => Nilai::with(['pengumpulan', 'siswa'])->findOrFail($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $nilai = Nilai::findOrFail($id);
        $payload = $request->validate([
            'score' => 'sometimes|numeric|min:0',
            'catatan' => 'nullable|string',
        ]);

        $nilai->update($payload);

        return response()->json([
            'message' => 'Nilai berhasil diupdate',
            'data' => $nilai,
        ]);
    }

    public function destroy($id)
    {
        Nilai::destroy($id);

        return response()->json([
            'message' => 'Nilai berhasil dihapus'
        ]);
    }
}
