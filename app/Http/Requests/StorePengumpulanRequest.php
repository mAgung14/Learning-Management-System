<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePengumpulanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tugas_id' => 'required|exists:tugas,id',
            'file' => 'required|file|max:10240',
        ];
    }
}
