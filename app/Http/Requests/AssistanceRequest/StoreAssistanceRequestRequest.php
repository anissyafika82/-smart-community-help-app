<?php

namespace App\Http\Requests\AssistanceRequest;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssistanceRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isRequester() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $helpOffer = $this->route('helpOffer');
        $maxQuantity = $helpOffer?->quantity ?? 1;

        return [
            'quantity' => ['required', 'integer', 'min:1', "max:{$maxQuantity}"],
            'priority' => ['nullable', 'in:low,medium,high'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
