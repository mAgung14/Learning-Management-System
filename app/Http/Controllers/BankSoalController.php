<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateSoalRequest;
use App\Http\Requests\UpdateBankSoalRequest;
use App\Jobs\GenerateSoalAiJob;
use App\Models\AiGenerateLog;
use App\Models\BankSoal;
use App\Models\Materi;
use App\Models\Tugas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankSoalController extends Controller
{

    // ════════════════════════════════════════════════════════════════
    //  POST /api/guru/bank-soal/generate
    //  Trigger AI generate soal (via Queue Job)
    // ════════════════════════════════════════════════════════════════
    public function generate(GenerateSoalRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $guru = $user->guru;

        if (!$guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak ditemukan',
            ], 404);
        }

        $payload = $request->validated();
        $tugasId = $payload['tugas_id'];
        $materiId = $payload['materi_id'];
        $prompt = $payload['prompt'];
        $jumlahSoal = $payload['jumlah_soal'] ?? 5;
        $tingkatKesulitan = $payload['tingkat_kesulitan'] ?? 'sedang';

        // Validasi kepemilikan tugas
        $tugas = Tugas::find($tugasId);
        if ($tugas->guru_id !== $guru->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda bukan pembuat tugas ini.',
            ], 403);
        }

        // Buat log record
        $log = AiGenerateLog::create([
            'tugas_id' => $tugasId,
            'materi_id' => $materiId,
            'guru_id' => $guru->id,
            'status' => 'pending',
            'jumlah_soal_diminta' => $jumlahSoal,
        ]);

        // Dispatch ke queue dengan parameter prompt
        GenerateSoalAiJob::dispatch(
            $tugasId,
            $materiId,
            $guru->id,
            $prompt,
            $jumlahSoal,
            $tingkatKesulitan,
            $log->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Proses generate soal AI telah dimulai. Soal akan tersedia dalam beberapa saat.',
            'data' => [
                'log_id' => $log->id,
                'status' => 'pending',
                'jumlah_soal_diminta' => $jumlahSoal,
                'tingkat_kesulitan' => $tingkatKesulitan,
            ],
        ], 202);
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /api/guru/bank-soal/status/{logId}
    //  Cek status generate AI
    // ════════════════════════════════════════════════════════════════
    public function status(int $logId): JsonResponse
    {
        $user = auth('api')->user();
        $log = AiGenerateLog::find($logId);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Log tidak ditemukan',
            ], 404);
        }

        if ($log->guru_id !== $user->guru->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak',
            ], 403);
        }

        $data = [
            'log_id' => $log->id,
            'status' => $log->status,
            'jumlah_soal_diminta' => $log->jumlah_soal_diminta,
            'jumlah_soal_generated' => $log->jumlah_soal_generated,
            'error_message' => $log->error_message,
            'created_at' => $log->created_at->toDateTimeString(),
            'updated_at' => $log->updated_at->toDateTimeString(),
        ];

        // Jika selesai, sertakan soal yang dihasilkan
        if ($log->status === 'completed') {
            $data['soal'] = BankSoal::where('tugas_id', $log->tugas_id)
                ->where('materi_id', $log->materi_id)
                ->where('guru_id', $log->guru_id)
                ->orderBy('urutan')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /api/guru/bank-soal?tugas_id=X
    //  List semua soal untuk tugas tertentu
    // ════════════════════════════════════════════════════════════════
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $guru = $user->guru;

        $query = BankSoal::with(['tugas', 'materi'])
            ->where('guru_id', $guru->id);

        // Filter by tugas_id
        if ($request->has('tugas_id')) {
            $query->where('tugas_id', $request->tugas_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by materi_id
        if ($request->has('materi_id')) {
            $query->where('materi_id', $request->materi_id);
        }

        $soalList = $query->orderBy('urutan')->get();

        return response()->json([
            'success' => true,
            'data' => $soalList,
            'total' => $soalList->count(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /api/guru/bank-soal/{id}
    //  Detail satu soal
    // ════════════════════════════════════════════════════════════════
    public function show(int $id): JsonResponse
    {
        $user = auth('api')->user();

        $soal = BankSoal::with(['tugas', 'materi'])->find($id);

        if (!$soal) {
            return response()->json([
                'success' => false,
                'message' => 'Soal tidak ditemukan',
            ], 404);
        }

        if ($soal->guru_id !== $user->guru->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $soal,
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  PUT /api/guru/bank-soal/{id}
    //  Edit soal (review sebelum publish)
    // ════════════════════════════════════════════════════════════════
    public function update(UpdateBankSoalRequest $request, int $id): JsonResponse
    {
        $user = auth('api')->user();

        $soal = BankSoal::find($id);

        if (!$soal) {
            return response()->json([
                'success' => false,
                'message' => 'Soal tidak ditemukan',
            ], 404);
        }

        if ($soal->guru_id !== $user->guru->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak',
            ], 403);
        }

        $soal->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Soal berhasil diupdate',
            'data' => $soal->fresh(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  DELETE /api/guru/bank-soal/{id}
    //  Hapus soal
    // ════════════════════════════════════════════════════════════════
    public function destroy(int $id): JsonResponse
    {
        $user = auth('api')->user();

        $soal = BankSoal::find($id);

        if (!$soal) {
            return response()->json([
                'success' => false,
                'message' => 'Soal tidak ditemukan',
            ], 404);
        }

        if ($soal->guru_id !== $user->guru->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak',
            ], 403);
        }

        $soal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Soal berhasil dihapus',
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /api/guru/bank-soal/publish
    //  Publish semua soal draft untuk tugas tertentu
    // ════════════════════════════════════════════════════════════════
    public function publish(Request $request): JsonResponse
    {
        $request->validate([
            'tugas_id' => 'required|integer|exists:tugas,id',
        ]);

        $user = auth('api')->user();
        $guru = $user->guru;

        $tugas = Tugas::find($request->tugas_id);
        if ($tugas->guru_id !== $guru->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak',
            ], 403);
        }

        $updated = BankSoal::where('tugas_id', $request->tugas_id)
            ->where('guru_id', $guru->id)
            ->where('status', 'draft')
            ->update(['status' => 'published']);

        return response()->json([
            'success' => true,
            'message' => "{$updated} soal berhasil dipublish",
            'data' => [
                'jumlah_published' => $updated,
            ],
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /api/guru/bank-soal/logs?tugas_id=X
    //  List log generate AI
    // ════════════════════════════════════════════════════════════════
    public function logs(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $guru = $user->guru;

        $query = AiGenerateLog::where('guru_id', $guru->id);

        if ($request->has('tugas_id')) {
            $query->where('tugas_id', $request->tugas_id);
        }

        $logs = $query->latest()->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'tugas_id' => $log->tugas_id,
                'materi_id' => $log->materi_id,
                'status' => $log->status,
                'jumlah_soal_diminta' => $log->jumlah_soal_diminta,
                'jumlah_soal_generated' => $log->jumlah_soal_generated,
                'error_message' => $log->error_message,
                'created_at' => $log->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}
