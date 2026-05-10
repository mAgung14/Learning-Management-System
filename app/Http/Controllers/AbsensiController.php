<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Siswa;
use Illuminate\Http\Request;

class AbsensiController extends Controller
{
    public function index()
    {
        $user = auth('api')->user();

        if ($user->role === 'siswa') {
            $siswa = Siswa::where('user_id', $user->id)->first();
            if (!$siswa) {
                return response()->json(['message' => 'Data siswa tidak ditemukan'], 404);
            }

            return response()->json(['data' => Absensi::where('siswa_id', $siswa->id)->with(['tugas', 'pengumpulan'])->get()]);
        }

        return response()->json(['data' => Absensi::with(['siswa', 'tugas', 'pengumpulan'])->get()]);
    }

    public function show($id)
    {
        return response()->json(['data' => Absensi::with(['siswa', 'tugas', 'pengumpulan'])->findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $payload = $request->validate([
            'status' => 'sometimes|string|max:50',
            'keterangan' => 'nullable|string',
            'kehadiran_pada' => 'sometimes|date',
        ]);

        $absensi = Absensi::findOrFail($id);
        $absensi->update($payload);

        return response()->json([
            'message' => 'Absensi berhasil diupdate',
            'data' => $absensi,
        ]);
    }

    public function destroy($id)
    {
        Absensi::destroy($id);

        return response()->json([
            'message' => 'Absensi berhasil dihapus'
        ]);
    }
}
