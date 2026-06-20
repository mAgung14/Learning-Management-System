<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Rombel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    /**
     * Import Mata Pelajaran dari Excel/CSV.
     *
     * Format Template Excel (Mulai Kolom A):
     * - Kolom A: kode_mapel (wajib, unik)
     * - Kolom B: nama_mapel (wajib)
     * - Kolom C: deskripsi (opsional)
     * - Kolom D: guru_ids (opsional, bisa menggunakan ID guru atau nama guru, dipisah koma jika lebih dari satu)
     *   - Jika menggunakan nama guru, nama harus persis sama dengan yang ada di database.
     *   - Jika ada beberapa guru dengan nama sama, gunakan ID guru untuk menghindari ambigu.
     * - Kolom E: rombel_ids (opsional, ID rombel dipisah koma jika lebih dari satu)
     *
     * Baris pertama harus header dan akan dilewati.
     */
    public function import(Request $request)
    {
        set_time_limit(900);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $file = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca file Excel/CSV: ' . $e->getMessage(),
            ], 400);
        }

        if (count($rows) <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'File Excel kosong atau hanya berisi header.'
            ], 400);
        }

        array_shift($rows);

        $errors = [];
        $validData = [];
        $seenCodes = [];

        $existingCodes = MataPelajaran::pluck('kode_mapel')->map(function ($value) {
            return strtolower(trim($value));
        })->toArray();
        $existingCodeSet = array_flip($existingCodes);

        $guruList = Guru::select('id', 'nama')->get();
        $guruIdSet = $guruList->pluck('id')->flip()->toArray();
        $guruNameMap = [];
        foreach ($guruList as $guru) {
            $nameKey = strtolower(trim($guru->nama));
            $guruNameMap[$nameKey][] = $guru->id;
        }

        $rombelIds = Rombel::pluck('id')->toArray();
        $rombelIdSet = array_flip($rombelIds);

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            $kodeMapel = isset($row[0]) ? trim((string)$row[0]) : '';
            $namaMapel = isset($row[1]) ? trim((string)$row[1]) : '';
            $deskripsi = isset($row[2]) ? trim((string)$row[2]) : '';
            $guruInput = isset($row[3]) ? trim((string)$row[3]) : '';
            $rombelInput = isset($row[4]) ? trim((string)$row[4]) : '';

            if ($kodeMapel === '' && $namaMapel === '' && $deskripsi === '' && $guruInput === '' && $rombelInput === '') {
                continue;
            }

            $rowErrors = [];
            $kodeLower = strtolower($kodeMapel);

            if (empty($kodeMapel)) {
                $rowErrors[] = 'Kode mapel wajib diisi.';
            } else {
                if (in_array($kodeLower, $seenCodes)) {
                    $rowErrors[] = 'Kode mapel ganda dalam file Excel.';
                } else {
                    $seenCodes[] = $kodeLower;
                }

                if (isset($existingCodeSet[$kodeLower])) {
                    $rowErrors[] = "Kode mapel '{$kodeMapel}' sudah terdaftar di database.";
                }
            }

            if (empty($namaMapel)) {
                $rowErrors[] = 'Nama mapel wajib diisi.';
            }

            $guruIdsForRow = [];
            if ($guruInput !== '') {
                $parsed = preg_split('/[;,]+/', $guruInput);
                foreach ($parsed as $guruItem) {
                    $guruItem = trim($guruItem);
                    if ($guruItem === '') {
                        continue;
                    }

                    if (ctype_digit($guruItem)) {
                        $guruIdInt = (int)$guruItem;
                        if (!isset($guruIdSet[$guruIdInt])) {
                            $rowErrors[] = "Guru dengan ID {$guruIdInt} tidak ditemukan.";
                        } else {
                            $guruIdsForRow[] = $guruIdInt;
                        }
                        continue;
                    }

                    $guruNameKey = strtolower($guruItem);
                    if (!isset($guruNameMap[$guruNameKey])) {
                        $rowErrors[] = "Guru dengan nama '{$guruItem}' tidak ditemukan.";
                        continue;
                    }

                    if (count($guruNameMap[$guruNameKey]) > 1) {
                        $rowErrors[] = "Nama guru '{$guruItem}' tidak unik. Gunakan ID guru yang spesifik.";
                        continue;
                    }

                    $guruIdsForRow[] = $guruNameMap[$guruNameKey][0];
                }
                $guruIdsForRow = array_values(array_unique($guruIdsForRow));
            }

            $rombelIdsForRow = [];
            if ($rombelInput !== '') {
                $parsed = preg_split('/[;,]+/', $rombelInput);
                foreach ($parsed as $rombelId) {
                    $rombelId = trim($rombelId);
                    if ($rombelId === '') {
                        continue;
                    }
                    if (!ctype_digit($rombelId)) {
                        $rowErrors[] = "Format rombel_ids tidak valid pada baris {$rowNum}. Gunakan ID rombel numerik yang dipisah koma.";
                        continue;
                    }
                    $rombelIdInt = (int)$rombelId;
                    if (!isset($rombelIdSet[$rombelIdInt])) {
                        $rowErrors[] = "Rombel dengan ID {$rombelIdInt} tidak ditemukan.";
                    } else {
                        $rombelIdsForRow[] = $rombelIdInt;
                    }
                }
                $rombelIdsForRow = array_values(array_unique($rombelIdsForRow));
            }

            if (!empty($rowErrors)) {
                $errors[] = [
                    'baris' => $rowNum,
                    'kode_mapel' => $kodeMapel ?: '-',
                    'nama_mapel' => $namaMapel ?: '-',
                    'errors' => $rowErrors,
                ];
                continue;
            }

            $validData[] = [
                'nama_mapel' => $namaMapel,
                'kode_mapel' => $kodeMapel,
                'deskripsi' => $deskripsi ?: null,
                'guru_ids' => $guruIdsForRow,
                'rombel_ids' => $rombelIdsForRow,
            ];
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Proses import dibatalkan karena terdapat data yang tidak valid.',
                'total_errors' => count($errors),
                'errors' => $errors,
            ], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($validData as $data) {
                $mapel = MataPelajaran::create([
                    'nama_mapel' => $data['nama_mapel'],
                    'kode_mapel' => $data['kode_mapel'],
                    'deskripsi' => $data['deskripsi'],
                ]);

                if (!empty($data['guru_ids'])) {
                    $mapel->guru()->sync($data['guru_ids']);
                }

                if (!empty($data['rombel_ids'])) {
                    $mapel->rombel()->sync($data['rombel_ids']);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil mengimport ' . count($validData) . ' data mata pelajaran.',
                'data' => [
                    'total_imported' => count($validData),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
