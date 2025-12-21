<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
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
            'nip' => 'required',
            'phone_number' => 'required',
            'email' => 'required|unique:employees,email',
            'address' => 'required',
            'user_id' => 'nullable|unique:users,id',
            'position_id' => 'required',
            'certifications' => 'nullable|array',
            'certifications.*' => 'exists:certifications,id',
        ];
    }
}
