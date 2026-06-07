<?php
namespace App\Http\Controllers;

use App\Models\Jurusan;
use Illuminate\Http\Request;

class JurusanController extends Controller
{
    /**
     * Menampilkan semua data jurusan.
     *
     * Endpoint ini mengembalikan daftar lengkap semua jurusan yang terdaftar di dalam sistem.
     * Hanya dapat diakses oleh Admin.
     *
     * @tags Jurusan
     * @response array{data: list<App\Models\Jurusan>}
     */
    public function index()
    {
        return response()->json([
            'data' => Jurusan::all()
        ]);
    }

    /**
     * Menambahkan jurusan baru.
     *
     * Endpoint ini digunakan oleh Admin untuk membuat data jurusan baru.
     * Nama jurusan harus unik dan tidak boleh kosong.
     *
     * @tags Jurusan
     * @bodyParam nama_jurusan string required Nama jurusan baru. Example: Akuntansi Keuangan Lembaga
     * @response 201 array{message: string, data: App\Models\Jurusan}
     */
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

    /**
     * Menampilkan detail jurusan.
     *
     * Endpoint ini mengembalikan informasi detail dari satu jurusan berdasarkan ID yang dicari.
     * Hanya dapat diakses oleh Admin.
     *
     * @tags Jurusan
     * @response array{data: App\Models\Jurusan}
     */
    public function show($id)
    {
        $jurusan = Jurusan::findOrFail($id);

        return response()->json([
            'data' => $jurusan
        ]);
    }

    /**
     * Memperbarui data jurusan.
     *
     * Endpoint ini digunakan oleh Admin untuk memperbarui nama jurusan yang sudah ada berdasarkan ID.
     * Nama jurusan baru harus unik.
     *
     * @tags Jurusan
     * @bodyParam nama_jurusan string required Nama jurusan baru yang diperbarui. Example: Teknik Komputer dan Jaringan
     * @response array{message: string, data: App\Models\Jurusan}
     */
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

    /**
     * Menghapus data jurusan.
     *
     * Endpoint ini digunakan oleh Admin untuk menghapus jurusan tertentu dari sistem berdasarkan ID.
     *
     * @tags Jurusan
     * @response array{message: string}
     */
    public function destroy($id)
    {
        $jurusan = Jurusan::findOrFail($id);
        $jurusan->delete();

        return response()->json([
            'message' => 'Jurusan berhasil dihapus'
        ]);
    }
}