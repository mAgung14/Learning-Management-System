<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul' => 'nullable|string',
            'deskripsi' => 'nullable|string',
            'kompetensi_dasar' => 'nullable|string',
            'indikator' => 'nullable|string',
            'tujuan_pembelajaran' => 'nullable|string',
            'mapel_id' => 'sometimes|exists:mata_pelajaran,id',
            'mapelId' => 'sometimes|exists:mata_pelajaran,id',
            'rombel_id' => 'sometimes|exists:rombel,id',
            'rombelId' => 'sometimes|exists:rombel,id',
            'is_published' => 'sometimes|boolean',
            'status' => 'sometimes|in:draft,submitted,approved',
            'pertemuans' => 'sometimes|json',
            'files' => 'sometimes|array',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar|max:20480',
        ];
    }
}
