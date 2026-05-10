<?php
namespace App\Http\Controllers;

use App\Models\Jurusan;
use Illuminate\Http\Request;

class JurusanController extends Controller
{
    // 🔥 GET ALL
    public function index()
    {
        return response()->json([
            'data' => Jurusan::all()
        ]);
    }

    // 🔥 STORE
    public function store(Request $request)
    {
        $request->validate([
            'nama_jurusan' => 'required|string|max:255|unique:jurusan,nama_jurusan'
        ]);

        $jurusan = Jurusan::create([
            'nama_jurusan' => $request->nama_jurusan
        ]);

        return response()->json([
            'message' => 'Jurusan berhasil ditambahkan',
            'data' => $jurusan
        ], 201);
    }

    // 🔥 SHOW
    public function show($id)
    {
        $jurusan = Jurusan::findOrFail($id);

        return response()->json([
            'data' => $jurusan
        ]);
    }

    // 🔥 UPDATE
    public function update(Request $request, $id)
    {
        $jurusan = Jurusan::findOrFail($id);

        $request->validate([
            'nama_jurusan' => 'required|string|max:255|unique:jurusan,nama_jurusan,' . $id
        ]);

        $jurusan->update([
            'nama_jurusan' => $request->nama_jurusan
        ]);

        return response()->json([
            'message' => 'Jurusan berhasil diupdate',
            'data' => $jurusan
        ]);
    }

    // 🔥 DELETE
    public function destroy($id)
    {
        $jurusan = Jurusan::findOrFail($id);
        $jurusan->delete();

        return response()->json([
            'message' => 'Jurusan berhasil dihapus'
        ]);
    }
}