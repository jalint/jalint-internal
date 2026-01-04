<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestParameterRequest extends FormRequest
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
            'code' => ['required',  Rule::unique('test_parameters', 'code')->ignore($this->route('test_parameter'))],
            'name' => ['required',  Rule::unique('test_parameters', 'name')->ignore($this->route('test_parameter'))],
            'unit' => 'required|string',
            'test_method_id' => 'required|exists:test_methods,id',
            'sample_type_id' => 'required|exists:sample_types,id',
            'price' => 'required|numeric|min:0',
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
