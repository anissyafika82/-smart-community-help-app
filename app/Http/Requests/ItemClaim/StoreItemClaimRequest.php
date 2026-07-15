<?php

namespace App\Http\Requests\ItemClaim;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreItemClaimRequest extends FormRequest
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
            'claim_message' => ['required', 'string', 'max:2000'],
            'proof_description' => ['nullable', 'string', 'max:2000'],
            'proof_image_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
