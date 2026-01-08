<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyBankAccountRequest extends FormRequest
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
            'bank_name' => ['sometimes', 'string', 'max:100'],
            'account_number' => ['sometimes', 'string', 'max:50'],
            'account_name' => ['sometimes', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:100'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'note' => ['nullable', 'string'],
        ];
    }
}
