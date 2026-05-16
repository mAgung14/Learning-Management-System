<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Kelas;

class KelasController extends Controller
{
     public function index()
    {
        $user = auth('api')->user();

        // Jika pemanggil adalah guru, filter kelas berdasarkan mata pelajaran yang dia ampu
        if ($user && $user->role === 'guru') {
            $guru = \App\Models\Guru::where('user_id', $user->id)->first();
            if ($guru) {
                $kelasIds = $guru->mapel()->pluck('kelas_id')->unique()->toArray();
                $kelas = Kelas::whereIn('id', $kelasIds)->get();

                return response()->json(['data' => $kelas]);
            }
            // Jika data guru tidak ditemukan, kembalikan array kosong
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => Kelas::all()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'tingkat' => 'required',
            'tahun_ajaran' => 'required'
        ]);

        $kelas = Kelas::create($request->all());

        return response()->json([
            'message' => 'Kelas berhasil dibuat',
            'data' => $kelas
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'data' => Kelas::findOrFail($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $kelas = Kelas::findOrFail($id);

        $kelas->update($request->all());

        return response()->json([
            'message' => 'Kelas berhasil diupdate',
            'data' => $kelas
        ]);
    }

    public function destroy($id)
    {
        Kelas::destroy($id);

        return response()->json([
            'message' => 'Kelas berhasil dihapus'
        ]);
    }
}
