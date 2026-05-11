<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Guru;
use App\Models\MataPelajaran;

class GuruMapelController extends Controller
{
    //  Assign banyak mapel ke guru
    public function assignMapel(Request $request, $id)
    {
        $request->validate([
            'mapel_id' => 'required|array',
            'mapel_id.*' => 'exists:mata_pelajaran,id'
        ]);

        $guru = Guru::findOrFail($id);

        // syncWithoutDetaching = tambah baru tanpa menghapus yang sudah ada
        $guru->mapel()->syncWithoutDetaching($request->mapel_id);

        return response()->json([
            'success' => true,
            'message' => 'Mapel berhasil di-assign',
        ]);
    }

    //  Ambil semua mapel yang diajar guru
    public function getMapel($id)
    {
        $guru = Guru::with('mapel:id,nama_mapel')
            ->select('id', 'nama', 'nik')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'guru' => [
                'id' => $guru->id,
                'nama' => $guru->nama,
                'nik' => $guru->nik
                ],
            'mapel' => $guru->mapel
            ]
        ]);
    }

    // Hapus 1 mapel dari guru
    public function removeMapel($id, $mapel_id)
    {
        $guru = Guru::findOrFail($id);

        $guru->mapel()->detach($mapel_id);

        return response()->json([
            'success' => true,
            'message' => 'Mapel berhasil dihapus dari guru'
        ]);
    }
}