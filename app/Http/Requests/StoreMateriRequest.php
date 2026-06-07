<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMateriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'mapel_id' => 'required|exists:mata_pelajaran,id',
            'rombel_id' => 'sometimes|nullable|exists:rombel,id',
            'files' => 'sometimes|array',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,png,jpg,jpeg,gif,mp4,mkv,webm|max:20480',
            'file1' => 'sometimes|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,png,jpg,jpeg,gif,mp4,mkv,webm|max:20480',
            'file2' => 'sometimes|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,png,jpg,jpeg,gif,mp4,mkv,webm|max:20480',
            'file3' => 'sometimes|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,png,jpg,jpeg,gif,mp4,mkv,webm|max:20480',
            'youtube_urls' => 'sometimes|array',
            'youtube_urls.*' => 'url',
        ];
    }
}
