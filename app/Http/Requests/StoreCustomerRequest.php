<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
            'name' => 'required',
            'customer_type_id' => 'required|exists:customer_types,id',
            'city' => 'required',
            'province' => 'required',
            'email' => 'required|unique:customers,email',
            'website' => 'nullable',
            'npwp' => 'nullable|unique:customers,npwp',
            'postal_code' => 'required',
            'status' => 'required',
            'address' => 'required',

            'pic_name' => 'required|string',
            'pic_email' => 'nullable',
            'pic_phone' => 'nullable',
            'pic_position' => 'required',
            'pic_npwp' => 'nullable',
        ];
    }
}
