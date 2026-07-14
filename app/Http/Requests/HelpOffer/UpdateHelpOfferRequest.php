<?php

namespace App\Http\Requests\HelpOffer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHelpOfferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $helpOffer = $this->route('helpOffer');

        return $this->user()?->isHelper()
            && $helpOffer
            && $helpOffer->helper_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'exists:categories,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'available_until' => ['nullable', 'date'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'location_address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['sometimes', 'in:available,claimed,completed,expired,cancelled'],
        ];
    }
}
