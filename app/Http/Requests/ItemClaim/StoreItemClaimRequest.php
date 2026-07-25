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
            'answers' => ['nullable', 'array'],
            'answers.*.verification_question_id' => ['required', 'integer', 'exists:verification_questions,id'],
            'answers.*.answer' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * If the target report has verification questions, the claimant must
     * answer every single one — no more, no fewer, no foreign question ids.
     *
     * @param \Illuminate\Validation\Validator $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $itemReport = $this->route('itemReport');
            if (! $itemReport) {
                return;
            }

            $requiredIds = $itemReport->verificationQuestions()->pluck('id')->sort()->values();
            if ($requiredIds->isEmpty()) {
                return;
            }

            $submittedIds = collect($this->input('answers', []))
                ->pluck('verification_question_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values();

            if ($requiredIds->all() !== $submittedIds->all()) {
                $validator->errors()->add(
                    'answers',
                    'You must answer all of the finder\'s verification questions to submit this claim.',
                );
            }
        });
    }
}
