<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerOfferRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'offer_date' => ['required', 'date'],
            'expired_date' => ['nullable', 'date', 'after_or_equal:offer_date'],
            'request_number' => ['nullable', 'string', 'max:100'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.test_parameter_id' => ['required', 'exists:test_parameters,id'],
            'details.*.test_package_id' => ['nullable', 'exists:test_packages,id'],
            'details.*.price' => ['required', 'numeric', 'min:0'],
            'details.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
