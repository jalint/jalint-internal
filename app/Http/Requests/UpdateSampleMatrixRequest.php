<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSampleMatrixRequest extends FormRequest
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
            'code' => ['required',  Rule::unique('sample_matrices', 'code')->ignore($this->route('sample_matrix'))],
            'sample_type_id' => 'required|exists:sample_types,id',
            'description' => 'nullable',
            'environment' => 'required',
        ];
    }
}
