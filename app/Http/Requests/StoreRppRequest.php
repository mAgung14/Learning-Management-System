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
            'judul' => 'nullable|string',
            'deskripsi' => 'nullable|string',
            'kompetensi_dasar' => 'nullable|string',
            'indikator' => 'nullable|string',
            'tujuan_pembelajaran' => 'nullable|string',
            'mapel_id' => 'required_without:mapelId|exists:mata_pelajaran,id',
            'mapelId' => 'required_without:mapel_id|exists:mata_pelajaran,id',
            'rombel_id' => 'required_without:rombelId|exists:rombel,id',
            'rombelId' => 'required_without:rombel_id|exists:rombel,id',
            'is_published' => 'sometimes|boolean',
            'status' => 'sometimes|in:draft,submitted,approved',
            'pertemuans' => 'sometimes|json',
            'files' => 'sometimes|array',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar|max:20480',
        ];
    }
}
