<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\MataPelajaran;
use Illuminate\Http\Request;

class MataPelajaranController extends Controller
{
    public function index(Request $request)
    {
        $query = MataPelajaran::select('id', 'nama_mapel', 'kode_mapel')
            ->with([
                'guru:id,nama'
            ]);

        $data = $query->get();

        return response()->json([
            'data' => $data->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nama_mapel' => $item->nama_mapel,
                    'kode_mapel' => $item->kode_mapel,
                    'guru' => $item->guru->pluck('nama')->implode(', ') ?: 'Belum ada guru',
                ];
            })
        ]);
    }

    public function formData()
    {
        return response()->json([
            'guru' => \App\Models\Guru::select('id', 'nama', 'nik')->get(),
            'rombel' => \App\Models\Rombel::with(['kelas:id,tingkat', 'jurusan:id,nama_jurusan'])->get()->map(function ($r) {
                return [
                    'id' => $r->id,
                    'nama_rombel' => trim(($r->kelas->tingkat ?? '') . ' ' . ($r->jurusan->nama_jurusan ?? ''))
                ];
            }),
        ]);
    }
    
    public function filterMapel(Request $request)
    {
        // Menambahkan whereDoesntHave('guru') untuk mengecualikan mapel yang sudah di-assign ke guru manapun
        $query = MataPelajaran::whereDoesntHave('guru');

        return response()->json([
            'data' => $query->select('id', 'nama_mapel')->get()
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
            'guru_ids' => 'sometimes|array',
            'guruIds' => 'sometimes|array',
            'guru_ids.*' => 'exists:guru,id',
            'guruIds.*' => 'exists:guru,id',
            'rombel_ids' => 'sometimes|array',
            'rombelIds' => 'sometimes|array',
            'rombel_ids.*' => 'exists:rombel,id',
            'rombelIds.*' => 'exists:rombel,id',
        ]);

        $data = [
            'nama_mapel' => $payload['nama_mapel'] ?? $payload['namaMapel'],
            'kode_mapel' => $payload['kode_mapel'] ?? $payload['kodeMapel'],
            'deskripsi' => $payload['deskripsi'] ?? null,
            ];

        $mapel = MataPelajaran::create($data);

        // Assign ke guru (karena 1 mapel bisa diajar banyak guru)
        $guruIds = $payload['guru_ids'] ?? $payload['guruIds'] ?? null;
        if ($guruIds !== null) {
            $mapel->guru()->sync($guruIds);
        }

        // Assign ke rombel (mapel diajarkan di rombel mana saja)
        $rombelIds = $payload['rombel_ids'] ?? $payload['rombelIds'] ?? null;
        if ($rombelIds !== null) {
            $mapel->rombel()->sync($rombelIds);
        }

        return response()->json([
            'message' => 'Mata pelajaran berhasil dibuat',
            'data' => $mapel->load(['guru:id,nama,nik', 'rombel.kelas:id,tingkat', 'rombel.jurusan:id,nama_jurusan']),
        ], 201);
    }

    public function show($id)
    {
        $data = MataPelajaran::with([
            'guru:id,nama,nik',
            'rombel.kelas:id,tingkat',
            'rombel.jurusan:id,nama_jurusan'
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
            'guru_ids' => 'sometimes|array',
            'guruIds' => 'sometimes|array',
            'guru_ids.*' => 'exists:guru,id',
            'guruIds.*' => 'exists:guru,id',
            'rombel_ids' => 'sometimes|array',
            'rombelIds' => 'sometimes|array',
            'rombel_ids.*' => 'exists:rombel,id',
            'rombelIds.*' => 'exists:rombel,id',
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

        $mapel->update($data);

        $guruIds = $payload['guru_ids'] ?? $payload['guruIds'] ?? null;
        if ($guruIds !== null) {
            // sync akan otomatis mereplace guru lama dengan guru yang ada di array baru ini
            $mapel->guru()->sync($guruIds);
        }

        $rombelIds = $payload['rombel_ids'] ?? $payload['rombelIds'] ?? null;
        if ($rombelIds !== null) {
            $mapel->rombel()->sync($rombelIds);
        }

        return response()->json([
            'message' => 'Mata pelajaran berhasil diupdate',
            'data' => $mapel->load(['guru:id,nama,nik', 'rombel.kelas:id,tingkat', 'rombel.jurusan:id,nama_jurusan']),
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
