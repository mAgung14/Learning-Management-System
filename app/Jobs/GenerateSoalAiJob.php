<?php

namespace App\Jobs;

use App\Models\AiGenerateLog;
use App\Models\Materi;
use App\Models\Tugas;
use App\Services\OpenAiService;
use App\Services\PdfExtractorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateSoalAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah retry jika job gagal.
     */
    public int $tries = 3;

    /**
     * Timeout dalam detik.
     */
    public int $timeout = 180;

    /**
     * Delay antar retry (dalam detik).
     */
    public int $backoff = 30;

    public function __construct(
        protected int $tugasId,
        protected int $materiId,
        protected int $guruId,
        protected string $prompt,
        protected int $jumlahSoal = 5,
        protected string $tingkatKesulitan = 'sedang',
        protected int $logId = 0,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(OpenAiService $openAi): void
    {
        // Ambil atau buat log record
        $log = $this->logId
            ? AiGenerateLog::findOrFail($this->logId)
            : AiGenerateLog::create([
                'tugas_id' => $this->tugasId,
                'materi_id' => $this->materiId,
                'guru_id' => $this->guruId,
                'status' => 'pending',
                'jumlah_soal_diminta' => $this->jumlahSoal,
            ]);

        try {
            // 1. Langsung kirim instruksi (prompt) ke OpenAI API
            Log::info("GenerateSoalAiJob: Sending prompt to OpenAI API", [
                'prompt_length' => strlen($this->prompt),
                'jumlah_soal' => $this->jumlahSoal,
            ]);

            $soalList = $openAi->generateSoalEssay(
                $this->prompt,
                $this->jumlahSoal,
                $this->tingkatKesulitan,
                $log
            );

            // 3. Simpan soal ke database
            Log::info("GenerateSoalAiJob: Saving soal to database", [
                'count' => count($soalList),
            ]);

            $savedSoal = $openAi->saveSoalToDatabase(
                $soalList,
                $this->tugasId,
                $this->materiId,
                $this->guruId
            );

            Log::info("GenerateSoalAiJob: Completed", [
                'log_id' => $log->id,
                'saved_count' => $savedSoal->count(),
            ]);

        } catch (\Exception $e) {
            Log::error("GenerateSoalAiJob: Failed", [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Update log jika ini attempt terakhir
            if ($this->attempts() >= $this->tries) {
                $log->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e; // Re-throw agar queue system bisa retry
        }
    }

    /**
     * Handle job failure setelah semua retry habis.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical("GenerateSoalAiJob: All retries exhausted", [
            'tugas_id' => $this->tugasId,
            'materi_id' => $this->materiId,
            'error' => $exception->getMessage(),
        ]);

        // Update log final
        if ($this->logId) {
            AiGenerateLog::where('id', $this->logId)->update([
                'status' => 'failed',
                'error_message' => 'Semua percobaan gagal: ' . $exception->getMessage(),
            ]);
        }
    }
}
