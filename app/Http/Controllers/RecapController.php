<?php

namespace App\Http\Controllers;

use App\Models\Rombel;
use App\Models\MataPelajaran;
use App\Models\Tugas;
use App\Models\Siswa;
use App\Models\Pengumpulan;
use App\Models\Nilai;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecapController extends Controller
{
    /**
     * Download Rekap Nilai per Rombel/Kelas.
     * 
     * Mengunduh rekapitulasi nilai siswa per rombel/kelas dalam format Excel (.xlsx).
     * Jika `mapel_id` diberikan, akan mengunduh detail rekap untuk mata pelajaran tersebut saja.
     * Jika `mapel_id` dikosongkan, akan mengunduh file Excel multi-sheet berisi:
     * - Sheet 1: Ringkasan rata-rata semua mata pelajaran untuk kelas tersebut.
     * - Sheet berikutnya: Detail tugas untuk masing-masing mata pelajaran.
     */
    public function downloadRecap(Request $request)
    {
        $payload = $request->validate([
            'rombel_id' => 'required|integer|exists:rombel,id',
            'mapel_id' => 'nullable|integer|exists:mata_pelajaran,id',
        ]);

        $rombelId = $payload['rombel_id'];
        $mapelId = $payload['mapel_id'] ?? null;

        $user = auth('api')->user();

        // 1. Validasi Akses Guru
        if ($user->role === 'guru') {
            $guru = $user->guru;
            if (!$guru) {
                return response()->json(['message' => 'Data guru tidak ditemukan.'], 404);
            }

            $guruMapelIds = $guru->mapel()->pluck('mata_pelajaran.id')->toArray();

            // Jika filter mapel_id diberikan, pastikan guru tersebut mengajar mapel tersebut
            if ($mapelId && !in_array($mapelId, $guruMapelIds)) {
                return response()->json(['message' => 'Akses ditolak. Anda tidak mengajar mata pelajaran ini.'], 403);
            }

            // Pastikan guru mengajar di rombel tersebut (ada mapel yang diajar oleh guru di rombel tersebut)
            $rombelHasGuruMapel = \DB::table('rombel_mapel')
                ->where('rombel_id', $rombelId)
                ->whereIn('mata_pelajaran_id', $guruMapelIds)
                ->exists();

            if (!$rombelHasGuruMapel) {
                return response()->json(['message' => 'Akses ditolak. Anda tidak mengajar di kelas ini.'], 403);
            }
        }

        // 2. Ambil data Rombel
        $rombel = Rombel::with(['kelas', 'jurusan'])->findOrFail($rombelId);
        $rombelName = trim(($rombel->kelas->tingkat ?? '') . ' ' . ($rombel->jurusan->nama_jurusan ?? ''));

        // 3. Ambil data semua siswa di Rombel ini
        $siswas = $rombel->siswa()->orderBy('nama', 'asc')->get();

        if ($siswas->isEmpty()) {
            return response()->json(['message' => 'Tidak ada siswa terdaftar di kelas/rombel ini.'], 404);
        }

        $spreadsheet = new Spreadsheet();

        if ($mapelId) {
            // EXPORT SINGLE MAPEL
            $mapel = MataPelajaran::findOrFail($mapelId);
            $sheet = $spreadsheet->getActiveSheet();
            
            // Judul Sheet & Tab
            $sheetTitle = $this->getUniqueSheetTitle($spreadsheet, $mapel->nama_mapel);
            $sheet->setTitle($sheetTitle);

            // Ambil daftar tugas untuk mapel ini di rombel ini
            $tugasList = Tugas::where('mapel_id', $mapelId)
                ->where(function ($q) use ($rombelId) {
                    $q->where('rombel_id', $rombelId)
                      ->orWhereNull('rombel_id');
                })
                ->orderBy('id', 'asc')
                ->get();

            $tugasIds = $tugasList->pluck('id')->toArray();

            // Ambil semua pengumpulan/nilai siswa untuk tugas-tugas ini
            $pengumpulans = Pengumpulan::with('nilai')
                ->whereIn('tugas_id', $tugasIds)
                ->get()
                ->groupBy('siswa_id');

            $this->populateMapelSheet($sheet, $mapel->nama_mapel, $rombelName, $tugasList, $siswas, $pengumpulans);

        } else {
            // EXPORT ALL MAPEL (MULTI-SHEET)
            // Tentukan mata pelajaran apa saja yang akan diexport
            if ($user->role === 'guru') {
                // Guru hanya bisa melihat mapel yang diajarkannya di rombel ini
                $guru = $user->guru;
                $guruMapelIds = $guru->mapel()->pluck('mata_pelajaran.id')->toArray();
                
                $mapels = MataPelajaran::whereIn('id', $guruMapelIds)
                    ->whereHas('rombel', function ($q) use ($rombelId) {
                        $q->where('rombel.id', $rombelId);
                    })->get();
            } else {
                // Admin bisa mengekspor semua mapel yang terdaftar di rombel ini
                $mapels = MataPelajaran::whereHas('rombel', function ($q) use ($rombelId) {
                    $q->where('rombel.id', $rombelId);
                })->get();
            }

            if ($mapels->isEmpty()) {
                return response()->json(['message' => 'Tidak ada mata pelajaran terdaftar untuk kelas/rombel ini.'], 404);
            }

            // Ambil daftar semua tugas dan pengumpulan/nilai untuk semua mapel di atas
            $mapelIds = $mapels->pluck('id')->toArray();
            $tugasListAll = Tugas::whereIn('mapel_id', $mapelIds)
                ->where(function ($q) use ($rombelId) {
                    $q->where('rombel_id', $rombelId)
                      ->orWhereNull('rombel_id');
                })
                ->get();

            $tugasIdsAll = $tugasListAll->pluck('id')->toArray();

            $pengumpulansAll = Pengumpulan::with('nilai')
                ->whereIn('tugas_id', $tugasIdsAll)
                ->get()
                ->groupBy('siswa_id');

            // Sheet 1: Ringkasan
            $ringkasanSheet = $spreadsheet->getActiveSheet();
            $ringkasanSheet->setTitle('Ringkasan');

            $this->populateRingkasanSheet($ringkasanSheet, $rombelName, $mapels, $siswas, $tugasListAll, $pengumpulansAll);

            // Sheet detail per Mapel
            foreach ($mapels as $mapel) {
                $newSheet = $spreadsheet->createSheet();
                $sheetTitle = $this->getUniqueSheetTitle($spreadsheet, $mapel->nama_mapel);
                $newSheet->setTitle($sheetTitle);

                $mapelTugas = $tugasListAll->where('mapel_id', $mapel->id)->values();
                $mapelTugasIds = $mapelTugas->pluck('id')->toArray();

                // Filter pengumpulan yang terkait dengan mapel ini
                $mapelPengumpulans = collect();
                foreach ($siswas as $siswa) {
                    $studentSubs = $pengumpulansAll->get($siswa->id) ?? collect();
                    $mapelSubs = $studentSubs->whereIn('tugas_id', $mapelTugasIds);
                    if ($mapelSubs->count() > 0) {
                        $mapelPengumpulans = $mapelPengumpulans->merge($mapelSubs);
                    }
                }

                $this->populateMapelSheet(
                    $newSheet,
                    $mapel->nama_mapel,
                    $rombelName,
                    $mapelTugas,
                    $siswas,
                    $mapelPengumpulans->groupBy('siswa_id')
                );
            }
        }

        // Set active sheet ke sheet pertama
        $spreadsheet->setActiveSheetIndex(0);

        // Generate response stream
        $writer = new Xlsx($spreadsheet);
        
        $filename = 'Rekap_Nilai_' . str_replace(' ', '_', $rombelName);
        if ($mapelId && isset($mapel)) {
            $filename .= '_' . str_replace(' ', '_', $mapel->nama_mapel);
        }
        $filename .= '_' . date('Ymd_His') . '.xlsx';

        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * Populasi sheet ringkasan rata-rata nilai mata pelajaran.
     */
    private function populateRingkasanSheet($sheet, $rombelName, $mapels, $siswas, $tugasListAll, $pengumpulansAll)
    {
        // Title Block
        $sheet->setCellValue('A1', 'RINGKASAN RATA-RATA NILAI KELAS');
        $sheet->setCellValue('A2', 'Kelas: ' . $rombelName);
        $sheet->setCellValue('A3', 'Tanggal Unduh: ' . date('d M Y, H:i'));
        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);

        // Headers
        $sheet->setCellValue('A5', 'No');
        $sheet->setCellValue('B5', 'NIS');
        $sheet->setCellValue('C5', 'Nama Siswa');

        $colChar = 'D';
        $mapelColMapping = [];
        foreach ($mapels as $mapel) {
            $sheet->setCellValue($colChar . '5', $mapel->nama_mapel);
            $mapelColMapping[$mapel->id] = $colChar;
            $colChar++;
        }

        $overallAvgCol = $colChar;
        $sheet->setCellValue($overallAvgCol . '5', 'Rata-rata Kelas');

        $lastColChar = $overallAvgCol;
        
        // Merge Title Block to prevent column A from expanding
        $sheet->mergeCells('A1:' . $lastColChar . '1');
        $sheet->mergeCells('A2:' . $lastColChar . '2');
        $sheet->mergeCells('A3:' . $lastColChar . '3');

        $headerRange = 'A5:' . $lastColChar . '5';
        
        // Header Style (Elegant Blue/Navy)
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'],
            ],
        ]);

        $row = 6;
        $no = 1;
        foreach ($siswas as $siswa) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValueExplicit('B' . $row, $siswa->nis, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('C' . $row, $siswa->nama);

            $studentSubmissions = $pengumpulansAll->get($siswa->id) ?? collect();
            $mapelAverages = [];

            foreach ($mapels as $mapel) {
                $mapelTugas = $tugasListAll->where('mapel_id', $mapel->id);
                $col = $mapelColMapping[$mapel->id];

                if ($mapelTugas->count() > 0) {
                    $totalScoreSum = 0;
                    foreach ($mapelTugas as $tugas) {
                        $sub = $studentSubmissions->firstWhere('tugas_id', $tugas->id);
                        $score = ($sub) ? ($sub->nilai?->score ?? $sub->nilai) : 0;
                        if ($score === null) {
                            $score = 0;
                        }
                        $totalScoreSum += $score;
                    }
                    $avg = $totalScoreSum / $mapelTugas->count();
                    $sheet->setCellValue($col . $row, round($avg, 1));
                    $mapelAverages[] = $avg;
                } else {
                    $sheet->setCellValue($col . $row, '-');
                }
            }

            if (count($mapelAverages) > 0) {
                $overallAvg = array_sum($mapelAverages) / count($mapelAverages);
                $sheet->setCellValue($overallAvgCol . $row, round($overallAvg, 1));
            } else {
                $sheet->setCellValue($overallAvgCol . $row, '-');
            }

            $row++;
        }

        // Apply styles to Ringkasan table
        $dataRange = 'A5:' . $lastColChar . ($row - 1);
        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D3D3D3'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A6:B' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $firstMapelCol = 'D';
        $sheet->getStyle($firstMapelCol . '6:' . $lastColChar . ($row - 1))
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Styling the overall average column
        $sheet->getStyle($overallAvgCol . '6:' . $overallAvgCol . ($row - 1))->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2'],
            ],
        ]);

        // Auto-fit column width (fixed width for 'No' column to look neat)
        for ($col = 'A'; $col !== $this->incrementColumn($lastColChar); $col++) {
            if ($col === 'A') {
                $sheet->getColumnDimension($col)->setAutoSize(false)->setWidth(6);
            } else {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
    }

    /**
     * Populasi sheet detail tugas per mata pelajaran.
     */
    private function populateMapelSheet($sheet, $mapelName, $rombelName, $tugasList, $siswas, $pengumpulans)
    {
        // Title Block
        $sheet->setCellValue('A1', 'REKAPITULASI NILAI TUGAS');
        $sheet->setCellValue('A2', 'Mata Pelajaran: ' . $mapelName);
        $sheet->setCellValue('A3', 'Kelas: ' . $rombelName);
        $sheet->setCellValue('A4', 'Tanggal Unduh: ' . date('d M Y, H:i'));
        $sheet->getStyle('A1:A3')->getFont()->setBold(true)->setSize(14);

        // Headers
        $sheet->setCellValue('A6', 'No');
        $sheet->setCellValue('B6', 'NIS');
        $sheet->setCellValue('C6', 'Nama Siswa');

        $colChar = 'D';
        $tugasColMapping = [];
        foreach ($tugasList as $tugas) {
            $sheet->setCellValue($colChar . '6', $tugas->judul);
            $tugasColMapping[$tugas->id] = $colChar;
            $colChar++;
        }

        $averageCol = $colChar;
        $sheet->setCellValue($averageCol . '6', 'Rata-rata');

        $lastColChar = $averageCol;
        
        // Merge Title Block to prevent column A from expanding
        $sheet->mergeCells('A1:' . $lastColChar . '1');
        $sheet->mergeCells('A2:' . $lastColChar . '2');
        $sheet->mergeCells('A3:' . $lastColChar . '3');
        $sheet->mergeCells('A4:' . $lastColChar . '4');

        $headerRange = 'A6:' . $lastColChar . '6';

        // Header style
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'],
            ],
        ]);

        $row = 7;
        $no = 1;
        foreach ($siswas as $siswa) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValueExplicit('B' . $row, $siswa->nis, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('C' . $row, $siswa->nama);

            $studentSubmissions = $pengumpulans->get($siswa->id) ?? collect();

            foreach ($tugasList as $tugas) {
                $sub = $studentSubmissions->firstWhere('tugas_id', $tugas->id);
                $score = null;
                if ($sub) {
                    $score = $sub->nilai?->score ?? $sub->nilai;
                }

                $col = $tugasColMapping[$tugas->id];
                if ($score !== null) {
                    $sheet->setCellValue($col . $row, $score);
                } else {
                    $sheet->setCellValue($col . $row, '-');
                }
            }

            // Calculate average
            if ($tugasList->count() > 0) {
                $totalScoreSum = 0;
                foreach ($tugasList as $tugas) {
                    $sub = $studentSubmissions->firstWhere('tugas_id', $tugas->id);
                    $score = ($sub) ? ($sub->nilai?->score ?? $sub->nilai) : 0;
                    if ($score === null) {
                        $score = 0;
                    }
                    $totalScoreSum += $score;
                }
                $avg = $totalScoreSum / $tugasList->count();
                $sheet->setCellValue($averageCol . $row, round($avg, 1));
            } else {
                $sheet->setCellValue($averageCol . $row, '-');
            }

            $row++;
        }

        // Apply styles to Mapel table
        $dataRange = 'A6:' . $lastColChar . ($row - 1);
        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D3D3D3'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A7:B' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        if ($tugasList->count() > 0) {
            $firstGradeCol = 'D';
            $sheet->getStyle($firstGradeCol . '7:' . $lastColChar . ($row - 1))
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Styling the average column
        $sheet->getStyle($averageCol . '7:' . $averageCol . ($row - 1))->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2'],
            ],
        ]);

        // Auto-fit column width (fixed width for 'No' column to look neat)
        for ($col = 'A'; $col !== $this->incrementColumn($lastColChar); $col++) {
            if ($col === 'A') {
                $sheet->getColumnDimension($col)->setAutoSize(false)->setWidth(6);
            } else {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
    }

    /**
     * Sanitasi & batasi panjang nama sheet agar valid di Excel.
     */
    private function getUniqueSheetTitle($spreadsheet, $name)
    {
        $sanitized = str_replace(['\\', '/', '?', '*', ':', '[', ']'], '', $name);
        $sanitized = substr($sanitized, 0, 31);
        if ($sanitized === '') {
            $sanitized = 'Sheet';
        }

        $finalTitle = $sanitized;
        $counter = 1;
        while ($spreadsheet->sheetNameExists($finalTitle)) {
            $suffix = ' ' . $counter;
            $finalTitle = substr($sanitized, 0, 31 - strlen($suffix)) . $suffix;
            $counter++;
        }
        return $finalTitle;
    }

    private function incrementColumn($col)
    {
        return ++$col;
    }
}
