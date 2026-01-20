<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreOfferRequest extends FormRequest
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
            'customer_id' => ['required', 'exists:customers,id'],
            'offer_date' => ['required', 'date'],
            'expired_date' => ['nullable', 'date', 'after_or_equal:offer_date'],
            'request_number' => ['nullable', 'string', 'max:100'],
            'template_id' => ['nullable', 'exists:templates,id'],
            'additional_description' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'testing_activities' => ['nullable', 'string'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'subtotal_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_draft' => ['required', 'boolean'],
            'is_dp' => ['required', 'boolean'],

            /* =====================
             | SAMPLES
             ===================== */

            // 'samples' => ['required', 'array', 'min:1'],
            // 'samples.*.title' => ['required', 'string', 'max:255'],
            // 'samples.*.parameters' => ['required', 'array', 'min:1'],
            // 'samples.*.parameters.*.test_parameter_id' => ['required', 'exists:test_parameters,id'],
            // 'samples.*.parameters.*.test_package_id' => ['nullable', 'exists:test_packages,id'],
            // 'samples.*.parameters.*.unit_price' => ['required', 'numeric', 'min:0'],
            // 'samples.*.parameters.*.qty' => ['required', 'integer', 'min:1'],

            'samples' => [
                Rule::requiredIf(fn () => $this->boolean('is_draft') === false),
                'array',
                'min:1',
            ],

            'samples.*.title' => ['required_with:samples', 'string', 'max:255'],
            'samples.*.parameters' => ['required_with:samples', 'array', 'min:1'],
            'samples.*.parameters.*.test_parameter_id' => [
                'required_with:samples',
                'exists:test_parameters,id',
            ],
            'samples.*.parameters.*.test_package_id' => [
                'nullable',
                'exists:test_packages,id',
            ],
            'samples.*.parameters.*.unit_price' => [
                'required_with:samples',
                'numeric',
                'min:0',
            ],
            'samples.*.parameters.*.qty' => [
                'required_with:samples',
                'integer',
                'min:1',
            ],
        ];
    }

    // protected function prepareForValidation()
    // {
    //     if ($this->offer_date) {
    //         $this->merge([
    //             'offer_date' => Carbon::createFromFormat('d-m-Y', $this->offer_date)
    //                 ->format('Y-m-d'),
    //         ]);
    //     }

    //     if ($this->expired_date) {
    //         $this->merge([
    //             'expired_date' => Carbon::createFromFormat('d-m-Y', $this->expired_date)
    //                 ->format('Y-m-d'),
    //         ]);
    //     }
    // }
}
