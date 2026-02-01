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
            'job_number' => ['required', 'string', 'max:100', 'unique:lhp_documents,job_number'],
            'tanggal_dilaporkan' => ['required', 'date_format:Y-m-d'],
            'tanggal_terima' => ['required', 'date_format:Y-m-d'], // Sesuaikan dengan payload

            // Hapus spasi setelah koma di dalam in:
            'status_contoh_uji' => ['required', 'in:diantar_pelanggan,diambil_oleh_laboratorium'],

            'details' => ['required', 'array', 'min:1'],
            'details.*.identifikasi_laboratorium' => ['required', 'string', 'max:255'],
            'details.*.identifikasi_contoh_uji' => ['required', 'string', 'max:255'],
            'details.*.sample_matrix_id' => ['required', 'exists:sample_matrices,id'], // Sebaiknya tambah exists
            'details.*.tanggal_pengambilan' => ['nullable', 'date_format:Y-m-d'],
            'details.*.waktu_pengambilan' => ['nullable', 'date_format:H:i'],
            'details.*.tanggal_penerimaan' => ['nullable', 'date_format:Y-m-d'],
            'details.*.waktu_penerimaan' => ['nullable', 'date_format:H:i'],
            'details.*.waktu_analisis_start' => ['nullable', 'date_format:Y-m-d'],
            'details.*.waktu_analisis_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:details.*.waktu_analisis_start'],

            'fppcu' => ['required', 'array', 'min:1'],
            'fppcu.*.nama_bahan_produk' => ['required', 'string', 'max:255'],
            'fppcu.*.jumlah_wadah_contoh_uji' => ['required', 'string', 'max:255'],

            'fppcu.*.details' => ['required', 'array', 'min:1'],
            // Tambahkan * lagi untuk mengakses isi array di dalam fppcu
            'fppcu.*.details.*.jenis_wadah' => ['required', 'string'],
            'fppcu.*.details.*.volume_contoh_uji' => ['required', 'string'],
            'fppcu.*.details.*.pengawetan' => ['required', 'string'],
            'fppcu.*.details.*.keterangan' => ['nullable', 'string'],
        ];
    }
}
