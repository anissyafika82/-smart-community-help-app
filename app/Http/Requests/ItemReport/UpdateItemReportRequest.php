<?php

namespace App\Http\Requests\ItemReport;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateItemReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $itemReport = $this->route('itemReport');

        return $itemReport && $itemReport->user_id === $this->user()?->id;
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
            'item_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'date_lost_or_found' => ['sometimes', 'date', 'before_or_equal:today'],
            'location_name' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'identifying_details' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'in:lost,found,potential_match,claimed,verified,returned,closed'],
        ];
    }
}
