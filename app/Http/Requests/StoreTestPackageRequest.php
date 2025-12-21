<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestPackageRequest extends FormRequest
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
            'name' => 'required|string|unique:test_packages,name',
            'price' => 'required|numeric|min:0',
            'sample_matrix_id' => 'required|exists:sample_matrices,id',
            'regulation_id' => 'required|exists:regulations,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'test_parameters' => 'required|array|min:1',
            'test_parameters.*' => 'exists:test_parameters,id',
        ];
    }
}
