<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreArImportRequest extends FormRequest
{
    /**
     * Only administrators can upload Accounts Receivable data.
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
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:51200'],
            'report_date' => ['required', 'date'],
            'sheet_name' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'File AR harus diunggah.',
            'file.mimes' => 'File harus berformat .xlsx, .xls, .csv, atau .txt.',
            'file.max' => 'Ukuran file maksimal 50 MB.',
            'report_date.required' => 'Tanggal laporan harus diisi.',
            'report_date.date' => 'Format tanggal laporan tidak valid.',
            'sheet_name.required' => 'Nama sheet Excel harus diisi.',
            'sheet_name.string' => 'Nama sheet harus berupa teks.',
            'sheet_name.max' => 'Nama sheet maksimal 50 karakter.',
        ];
    }
}
