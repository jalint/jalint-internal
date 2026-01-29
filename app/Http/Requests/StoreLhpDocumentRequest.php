<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLhpDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'offer_id' => ['required', 'exists:offers,id'],

            'job_number' => [
                'required',
                'string',
                'max:100',
                'unique:lhp_documents,job_number',
            ],

            'tanggal_dilaporkan' => [
                'required',
                'date_format:Y-m-d',
            ],

            'details' => [
                'required',
                'array',
                'min:1',
            ],

            'details.*.identifikasi_laboratorium' => [
                'required',
                'string',
                'max:255',
            ],

            'details.*.identifikasi_contoh_uji' => [
                'required',
                'string',
                'max:255',
            ],

            'details.*.sample_matrix_id' => [
                'required',
            ],

            'details.*.tanggal_pengambilan' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'details.*.waktu_pengambilan' => [
                'nullable',
                'date_format:H:i',
            ],

            'details.*.tanggal_penerimaan' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'details.*.waktu_penerimaan' => [
                'nullable',
                'date_format:H:i',
            ],

            'details.*.waktu_analisis_start' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'details.*.waktu_analisis_end' => [
                'nullable',
                'date_format:Y-m-d',
                'after:details.*.waktu_analisis_start',
            ],

            'details.*.koordinat_lintang' => [
                'nullable',
            ],

            'details.*.koordinat_bujur' => [
                'nullable',
            ],
        ];
    }
}
