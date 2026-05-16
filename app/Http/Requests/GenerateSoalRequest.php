<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tugas_id' => 'required|integer|exists:tugas,id',
            'materi_id' => 'required|integer|exists:materi,id',
            'prompt' => 'required|string|min:10',
            'jumlah_soal' => 'sometimes|integer|min:1|max:20',
            'tingkat_kesulitan' => 'sometimes|string|in:mudah,sedang,sulit',
        ];
    }

    public function messages(): array
    {
        return [
            'tugas_id.required' => 'ID Tugas wajib diisi',
            'tugas_id.exists' => 'Tugas tidak ditemukan',
            'materi_id.required' => 'ID Materi wajib diisi',
            'materi_id.exists' => 'Materi tidak ditemukan',
            'prompt.required' => 'Instruksi (prompt) wajib diisi',
            'prompt.min' => 'Instruksi minimal 10 karakter',
            'jumlah_soal.min' => 'Jumlah soal minimal 1',
            'jumlah_soal.max' => 'Jumlah soal maksimal 20',
            'tingkat_kesulitan.in' => 'Tingkat kesulitan harus: mudah, sedang, atau sulit',
        ];
    }
}
