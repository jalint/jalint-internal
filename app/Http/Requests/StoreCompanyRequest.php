<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
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
            'name' => ['required'],
            'province' => ['required'],
            'city' => ['required'],
            'postal_code' => 'required',
            'email' => ['required', 'email'],
            'npwp' => 'required',
            'phone' => 'required',
            'address' => 'required',
        ];
    }
}
