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
        $rules = [
            'tugas_id' => 'required|exists:tugas,id',
            'file' => 'required_without:link|nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,png,jpg,jpeg,gif,mp4,mkv,webm|max:10240',
            'link' => 'required_without:file|nullable|string|url|max:1000',
        ];

        // If the student already has a submission, relax the rules so both fields are optional
        $user = auth('api')->user();
        if ($user && $user->role === 'siswa') {
            $siswa = \App\Models\Siswa::where('user_id', $user->id)->first();
            if ($siswa) {
                $tugasId = $this->input('tugas_id') ?? $this->input('tugasId');
                if ($tugasId) {
                    $pengumpulan = \App\Models\Pengumpulan::where('tugas_id', $tugasId)
                        ->where('siswa_id', $siswa->id)
                        ->first();
                    if ($pengumpulan) {
                        $rules['file'] = 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,png,jpg,jpeg,gif,mp4,mkv,webm|max:10240';
                        $rules['link'] = 'nullable|string|url|max:1000';
                    }
                }
            }
        }

        return $rules;
    }

    protected function prepareForValidation()
    {
        if ($this->has('link')) {
            $link = $this->input('link');
            if (is_string($link)) {
                $link = trim($link);
                if ($link === 'null' || $link === 'undefined' || $link === '') {
                    $this->merge(['link' => null]);
                } elseif (!preg_match('~^(?:f|ht)tps?://~i', $link)) {
                    $this->merge(['link' => 'https://' . $link]);
                }
            }
        }

        if ($this->has('file')) {
            $file = $this->input('file');
            // If the file parameter is sent as a string (which could be "null", "undefined",
            // empty, or the URL of the existing file), we remove it so validation passes.
            if (is_string($file)) {
                $this->request->remove('file');
            }
        }
    }
}
