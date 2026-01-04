<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // =====================
            // OFFER HEADER
            // =====================
            'title' => ['required', 'string', 'max:255'],
            'offer_date' => ['required', 'date'],
            'expired_date' => ['required', 'date', 'after_or_equal:offer_date'],
            'request_number' => ['nullable', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:255'],

            // =====================
            // SAMPLES
            // =====================
            'samples' => ['required', 'array', 'min:1'],

            'samples.*.title' => [
                'required',
                'string',
                'max:255',
            ],

            'samples.*.parameters' => [
                'required',
                'array',
                'min:1',
            ],

            // =====================
            // SAMPLE PARAMETERS
            // =====================
            'samples.*.parameters.*.test_parameter_id' => [
                'required',
                'exists:test_parameters,id',
            ],

            'samples.*.parameters.*.price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'samples.*.parameters.*.qty' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * Optional: custom attribute names (biar error FE enak dibaca).
     */
    public function attributes(): array
    {
        return [
            'samples.*.title' => 'judul contoh uji',
            'samples.*.parameters.*.test_parameter_id' => 'parameter uji',
            'samples.*.parameters.*.price' => 'harga parameter',
            'samples.*.parameters.*.qty' => 'jumlah uji',
        ];
    }
}
