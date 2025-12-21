<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
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
            'email' => ['required',  Rule::unique('customers', 'email')->ignore($this->route('customer'))],
            'website' => 'nullable',
            'npwp' => ['nullable',  Rule::unique('customers', 'npwp')->ignore($this->route('customer'))],
            'postal_code' => 'required',
            'status' => 'required',
            'address' => 'required',
        ];
    }
}
