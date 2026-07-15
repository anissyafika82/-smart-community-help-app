<?php

namespace App\Http\Requests\Report;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reported_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'item_claim_id' => ['nullable', 'integer', 'exists:item_claims,id'],
            'reason' => ['required', 'in:fake_item,spam,inappropriate_behaviour,other'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('reported_user_id') && ! $this->filled('item_claim_id')) {
                $validator->errors()->add('reported_user_id', 'Report must target either a user or a claim.');
            }
        });
    }
}
