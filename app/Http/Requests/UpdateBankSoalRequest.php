<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankSoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pertanyaan' => 'sometimes|string|min:10',
            'jawaban' => 'sometimes|nullable|string',
            'tingkat_kesulitan' => 'sometimes|in:mudah,sedang,sulit',
            'status' => 'sometimes|in:draft,published',
            'urutan' => 'sometimes|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'pertanyaan.min' => 'Pertanyaan minimal 10 karakter',
            'tingkat_kesulitan.in' => 'Tingkat kesulitan harus: mudah, sedang, atau sulit',
            'status.in' => 'Status harus: draft atau published',
        ];
    }
}
