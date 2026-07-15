<?php

namespace App\Http\Requests\ItemReport;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreItemReportRequest extends FormRequest
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
            'report_type' => ['required', 'in:lost,found'],
            'category_id' => ['required', 'exists:categories,id'],
            'item_name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'date_lost_or_found' => ['required', 'date', 'before_or_equal:today'],
            'location_name' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'identifying_details' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
