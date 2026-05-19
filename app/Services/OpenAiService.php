<?php

namespace App\Services;

use App\Models\AiGenerateLog;
use App\Models\BankSoal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
        $this->baseUrl = 'https://api.openai.com/v1';

        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenAI API key belum dikonfigurasi. Set OPENAI_API_KEY di .env');
        }
    }

    /**
     * Generate soal essay berdasarkan instruksi (prompt) guru menggunakan Gemini AI.
     *
     * @param  string  $promptGuru     Instruksi / Topik dari guru
     * @param  int     $jumlahSoal     Jumlah soal yang diminta
     * @param  string  $tingkatKesulitan  mudah|sedang|sulit
     * @param  AiGenerateLog  $log     Log record untuk tracking progress
     * @return array   Array of parsed soal data
     *
     * @throws \RuntimeException
     */
    public function generateSoalEssay(
        string $promptGuru,
        int $jumlahSoal = 5,
        string $tingkatKesulitan = 'sedang',
        AiGenerateLog $log
    ): array {
        // Update log status
        $log->update(['status' => 'processing']);

        // Buat prompt
        $prompt = $this->buildPrompt($promptGuru, $jumlahSoal, $tingkatKesulitan);

        try {
            // Kirim request ke OpenAI API
            $response = $this->callOpenAiApi($prompt);
            
            // Simpan raw response ke log
            $log->update(['raw_response' => json_encode($response, JSON_UNESCAPED_UNICODE)]);

            // Parse response JSON dari AI
            $soalList = $this->parseAiResponse($response);

            // Validasi jumlah soal
            if (empty($soalList)) {
                throw new \RuntimeException('AI tidak menghasilkan soal yang valid');
            }

            // Update log sukses
            $log->update([
                'status' => 'completed',
                'jumlah_soal_generated' => count($soalList),
            ]);

            return $soalList;

        } catch (\Exception $e) {
            // Update log gagal
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('OpenAI generation failed', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Build prompt untuk OpenAI API.
     */
    protected function buildPrompt(string $promptGuru, int $jumlahSoal, string $tingkatKesulitan): string
    {
        return <<<PROMPT
Kamu adalah seorang guru profesional yang ahli dalam membuat soal ujian.
Tugasmu adalah membuat soal ESSAY berdasarkan topik atau instruksi berikut:

TOPIK/INSTRUKSI:
"{$promptGuru}"

## ATURAN WAJIB:
1. Buat tepat {$jumlahSoal} soal essay
2. Semua soal HARUS dalam Bahasa Indonesia
3. Soal harus sesuai dengan instruksi yang diberikan di atas
4. Tingkat kesulitan: {$tingkatKesulitan}
5. Setiap soal harus memiliki jawaban yang jelas dan lengkap
6. Soal harus bervariasi (tidak boleh mirip satu sama lain)

## FORMAT RESPONSE:
Response WAJIB dalam format JSON yang valid. Tidak boleh ada text lain di luar JSON.
Gunakan format berikut:

```json
{
  "soal": [
    {
      "nomor": 1,
      "pertanyaan": "Teks soal essay di sini",
      "jawaban": "Jawaban lengkap di sini",
      "tingkat_kesulitan": "{$tingkatKesulitan}"
    }
  ]
}
```

## PENTING:
- Response HANYA berisi JSON valid, tanpa penjelasan atau pengantar apapun.
PROMPT;
    }

    /**
     * Generate teks deskripsi tugas berdasarkan prompt guru.
     */
    public function generateDeskripsi(string $promptGuru): string
    {
        $prompt = <<<PROMPT
Kamu adalah asisten guru yang ahli dalam membuat deskripsi atau instruksi tugas yang jelas, menarik, dan mudah dipahami siswa.
Tugasmu adalah membuat teks deskripsi untuk suatu Tugas/Assignment berdasarkan instruksi berikut:

INSTRUKSI GURU:
"{$promptGuru}"

## ATURAN WAJIB:
1. Gunakan Bahasa Indonesia yang baik, ramah, dan profesional.
2. Buat deskripsi yang rapi, bisa gunakan formatting markdown (seperti bold, list).
3. JANGAN menggunakan kalimat pembuka/penutup seperti "Tentu, ini deskripsinya", langsung berikan teks deskripsi utamanya saja.
4. JANGAN bungkus response dengan JSON, langsung format plain text / markdown.
PROMPT;

        try {
            $response = $this->callOpenAiApi($prompt);
            $text = $response['choices'][0]['message']['content'] ?? null;
            
            if (empty($text)) {
                throw new \RuntimeException('AI tidak menghasilkan teks.');
            }
            
            return trim($text);
        } catch (\Exception $e) {
            Log::error('OpenAI generate deskripsi failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Kirim request ke OpenAI API.
     */
    protected function callOpenAiApi(string $prompt): array
    {
        $url = "{$this->baseUrl}/chat/completions";

        $response = Http::withToken($this->apiKey)
            ->timeout(120)
            ->retry(2, 5000) // Retry 2 kali dengan jeda 5 detik
            ->post($url, [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 4096,
                'response_format' => [
                    'type' => 'json_object' // Pastikan prompt meminta JSON
                ]
            ]);

        if ($response->failed()) {
            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? 'Unknown OpenAI API error';

            Log::error('OpenAI API request failed', [
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);

            throw new \RuntimeException(
                "OpenAI API error ({$response->status()}): {$errorMessage}"
            );
        }

        return $response->json();
    }

    /**
     * Parse response dari OpenAI API menjadi array soal.
     */
    protected function parseAiResponse(array $response): array
    {
        // Ambil text dari response OpenAI
        $text = $response['choices'][0]['message']['content'] ?? null;

        if (empty($text)) {
            throw new \RuntimeException('Response OpenAI tidak mengandung text');
        }

        // Bersihkan response - hapus markdown code blocks jika ada
        $text = $this->cleanJsonResponse($text);

        // Parse JSON
        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('JSON parse failed, attempting fallback', [
                'raw_text' => substr($text, 0, 500),
                'json_error' => json_last_error_msg(),
            ]);

            // Fallback: coba extract JSON dari text
            $decoded = $this->fallbackJsonParse($text);
        }

        // Validasi struktur
        $soalList = $decoded['soal'] ?? $decoded['questions'] ?? $decoded;

        if (!is_array($soalList)) {
            throw new \RuntimeException('Format response AI tidak valid: bukan array');
        }

        // Normalisasi setiap soal
        return array_map(function ($soal, $index) {
            return [
                'nomor' => $soal['nomor'] ?? ($index + 1),
                'pertanyaan' => $soal['pertanyaan'] ?? $soal['question'] ?? '',
                'jawaban' => $soal['jawaban'] ?? $soal['answer'] ?? '',
                'tingkat_kesulitan' => $soal['tingkat_kesulitan'] ?? 'sedang',
            ];
        }, $soalList, array_keys($soalList));
    }

    /**
     * Bersihkan response JSON dari OpenAI.
     * Kadang OpenAI mengirim response dalam markdown code block.
     */
    protected function cleanJsonResponse(string $text): string
    {
        // Hapus markdown code block ```json ... ```
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        // Hapus karakter BOM jika ada
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

        return trim($text);
    }

    /**
     * Fallback parsing: coba extract JSON dari text yang mixed.
     */
    protected function fallbackJsonParse(string $text): array
    {
        // Coba cari JSON object di dalam text
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Coba cari JSON array di dalam text
        if (preg_match('/\[[\s\S]*\]/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return ['soal' => $decoded];
            }
        }

        throw new \RuntimeException(
            'Gagal parse response AI ke JSON. Raw text: ' . substr($text, 0, 200)
        );
    }

    /**
     * Potong text jika melebihi batas karakter.
     * Memastikan tidak memotong di tengah kalimat.
     */
    protected function truncateText(string $text, int $maxChars): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $maxChars);

        // Cari titik terakhir untuk potong di akhir kalimat
        $lastPeriod = mb_strrpos($truncated, '.');
        if ($lastPeriod !== false && $lastPeriod > $maxChars * 0.8) {
            $truncated = mb_substr($truncated, 0, $lastPeriod + 1);
        }

        return $truncated;
    }

    /**
     * Simpan soal hasil generate ke database.
     *
     * @param  array  $soalList    Array soal dari parseAiResponse
     * @param  int    $tugasId     ID tugas terkait
     * @param  int    $materiId    ID materi referensi
     * @param  int    $guruId      ID guru yang generate
     * @return \Illuminate\Support\Collection  Collection of BankSoal
     */
    public function saveSoalToDatabase(array $soalList, int $tugasId, int $materiId, int $guruId)
    {
        $savedSoal = collect();

        foreach ($soalList as $index => $soal) {
            // Skip soal yang kosong
            if (empty($soal['pertanyaan'])) {
                continue;
            }

            $bankSoal = BankSoal::create([
                'tugas_id' => $tugasId,
                'materi_id' => $materiId,
                'guru_id' => $guruId,
                'pertanyaan' => $soal['pertanyaan'],
                'jawaban' => $soal['jawaban'] ?? null,
                'tipe' => 'essay',
                'tingkat_kesulitan' => $this->normalizeTingkatKesulitan($soal['tingkat_kesulitan'] ?? 'sedang'),
                'status' => 'draft',
                'urutan' => $index + 1,
            ]);

            $savedSoal->push($bankSoal);
        }

        return $savedSoal;
    }

    /**
     * Normalize tingkat kesulitan value.
     */
    protected function normalizeTingkatKesulitan(string $value): string
    {
        $map = [
            'mudah' => 'mudah',
            'easy' => 'mudah',
            'sedang' => 'sedang',
            'medium' => 'sedang',
            'sulit' => 'sulit',
            'hard' => 'sulit',
            'difficult' => 'sulit',
        ];

        return $map[strtolower(trim($value))] ?? 'sedang';
    }
}
