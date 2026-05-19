<?php

namespace App\Http\Controllers;

use App\Services\OpenAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    /**
     * Generate deskripsi tugas berdasarkan prompt guru secara synchronous.
     * Tidak menyimpan ke database, langsung return hasil AI.
     */
    public function generateDeskripsi(Request $request, OpenAiService $openAi): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|min:5',
        ]);

        try {
            $deskripsi = $openAi->generateDeskripsi($request->prompt);

            return response()->json([
                'success' => true,
                'data' => [
                    'deskripsi' => $deskripsi
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghasilkan deskripsi: ' . $e->getMessage(),
            ], 500);
        }
    }
}
