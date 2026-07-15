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
            'assistance_request_id' => ['nullable', 'integer', 'exists:assistance_requests,id'],
            'reason' => ['required', 'in:fake_request,spam,inappropriate_behaviour,other'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('reported_user_id') && ! $this->filled('assistance_request_id')) {
                $validator->errors()->add('reported_user_id', 'Report must target either a user or a request.');
            }
        });
    }
}
