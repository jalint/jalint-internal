<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestParameterRequest extends FormRequest
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
            'code' => 'required|unique:test_parameters,code',
            'name' => 'required|unique:test_parameters,name',
            'unit' => 'required|string',
            'test_method_id' => 'required|exists:test_methods,id',
            'sample_type_id' => 'required|exists:sample_types,id',
            'price' => 'required',
            'standard_min_value' => 'nullable',
            'standard_max_value' => 'nullable',
            'standard_unit' => 'nullable',
            'status' => 'required|in:A,N/A,S',
            'standard_note' => 'nullable',
            'description' => 'nullable',
        ];
    }

    public function attributes(): array
    {
        return [
            'test_method_id' => 'test method',
        ];
    }
}
