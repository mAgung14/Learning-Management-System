<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kompetensi_dasar' => 'nullable|string',
            'indikator' => 'nullable|string',
            'tujuan_pembelajaran' => 'nullable|string',
            'mapel_id' => 'required|exists:mata_pelajaran,id',
            'rombel_id' => 'required|exists:rombel,id',
            'status' => 'sometimes|in:draft,submitted,approved',
            'pertemuans' => 'sometimes|json',
            'files' => 'sometimes|array',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar|max:20480',
        ];
    }
}
