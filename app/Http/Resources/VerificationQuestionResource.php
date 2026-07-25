<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The claimant-facing shape: question text only. expected_answer is
 * deliberately never serialized here — see ItemClaimController::answers()
 * for the finder-only equivalent that includes it.
 */
class VerificationQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
        ];
    }
}
