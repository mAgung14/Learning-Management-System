<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\MataPelajaran;
use Illuminate\Http\Request;

class MataPelajaranController extends Controller
{
    public function index()
    {
        $data = MataPelajaran::select('id', 'nama_mapel', 'kode_mapel', 'kelas_id', 'jurusan_id')
    ->with([
        'kelas:id,tingkat',
        'jurusan:id,nama_jurusan'
    ])
    ->get();

return response()->json([
    'data' => $data->map(function ($item) {
        return [
            'id' => $item->id,
            'nama_mapel' => $item->nama_mapel,
            'kode_mapel' => $item->kode_mapel,
            'tingkat' => $item->kelas->tingkat ,
            'nama_jurusan' => $item->jurusan->nama_jurusan ,
        ];
    })
]);
    }
    public function filterMapel(Request $request)
    {
        $kelasId = $request->input('kelas_id');
        $jurusanId = $request->input('jurusan_id');

    return response()->json([
        'debug' => [
            'kelas_id' => $kelasId,
            'jurusan_id' => $jurusanId
        ],
        'data' => MataPelajaran::where('kelas_id', $kelasId)
            ->where('jurusan_id', $jurusanId)
            ->select('id', 'nama_mapel')
            ->get()
    ]);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'nama_mapel' => 'required|string|max:255',
            'namaMapel' => 'sometimes|string|max:255',
            'kode_mapel' => 'required|string|max:255|unique:mata_pelajaran,kode_mapel',
            'kodeMapel' => 'sometimes|string|max:255',
            'deskripsi' => 'nullable|string',
            'kelas_id' => 'required|exists:kelas,id',
            'jurusan_id' => 'sometimes|exists:jurusan,id',
        ]);

        $data = [
            'nama_mapel' => $payload['nama_mapel'] ?? $payload['namaMapel'],
            'kode_mapel' => $payload['kode_mapel'] ?? $payload['kodeMapel'],
            'deskripsi' => $payload['deskripsi'] ?? null,
            'kelas_id' => $payload['kelas_id'],
            'jurusan_id' => $payload['jurusan_id'],
        ];

        $mapel = MataPelajaran::create($data);

        return response()->json([
            'message' => 'Mata pelajaran berhasil dibuat',
            'data' => $mapel,
        ], 201);
    }

    public function show($id)
    {
        $data = MataPelajaran::with([
            'kelas:id,tingkat',
            'jurusan:id,nama_jurusan'
        ])->findOrFail($id);

        return response()->json([
            'data' => $data
    ]);
    }

    public function update(Request $request, $id)
    {
        $mapel = MataPelajaran::findOrFail($id);

        $payload = $request->validate([
            'nama_mapel' => 'sometimes|string|max:255',
            'namaMapel' => 'sometimes|string|max:255',
            'kode_mapel' => 'sometimes|string|max:255|unique:mata_pelajaran,kode_mapel,' . $mapel->id,
            'kodeMapel' => 'sometimes|string|max:255',
            'deskripsi' => 'nullable|string',
            'kelas_id' => 'sometimes|exists:kelas,id',
            'kelasId' => 'sometimes|exists:kelas,id',
            'jurusan_id' => 'sometimes|exists:jurusan,id',
        ]);

        $data = [];
        if (isset($payload['nama_mapel'])) {
            $data['nama_mapel'] = $payload['nama_mapel'];
        }
        if (isset($payload['namaMapel'])) {
            $data['nama_mapel'] = $payload['namaMapel'];
        }
        if (isset($payload['kode_mapel'])) {
            $data['kode_mapel'] = $payload['kode_mapel'];
        }
        if (isset($payload['kodeMapel'])) {
            $data['kode_mapel'] = $payload['kodeMapel'];
        }
        if (array_key_exists('deskripsi', $payload)) {
            $data['deskripsi'] = $payload['deskripsi'];
        }
        if (isset($payload['kelas_id'])) {
            $data['kelas_id'] = $payload['kelas_id'];
        }
        if (isset($payload['kelasId'])) {
            $data['kelas_id'] = $payload['kelasId'];
        }
        if (isset($payload['jurusan_id'])) {
            $data['jurusan_id'] = $payload['jurusan_id'];
        }

        $mapel->update($data);

        return response()->json([
            'message' => 'Mata pelajaran berhasil diupdate',
            'data' => $mapel,
        ]);
    }

    public function destroy($id)
    {
        MataPelajaran::destroy($id);

        return response()->json([
            'message' => 'Mata pelajaran berhasil dihapus'
        ]);
    }
}
