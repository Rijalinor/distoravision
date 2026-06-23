<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    /**
     * Only administrators can upload secondary sales data.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:51200'],
            'period' => ['required', 'date_format:Y-m'],
            'import_mode' => ['required', 'in:tambah,ganti'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'File data harus diunggah.',
            'file.mimes' => 'File harus berformat .csv, .txt, .xlsx, atau .xls.',
            'file.max' => 'Ukuran file maksimal 50 MB.',
            'period.required' => 'Periode harus diisi.',
            'period.date_format' => 'Format periode harus YYYY-MM (contoh: 2026-05).',
            'import_mode.required' => 'Mode import harus dipilih.',
            'import_mode.in' => 'Mode import harus "tambah" atau "ganti".',
        ];
    }
}
