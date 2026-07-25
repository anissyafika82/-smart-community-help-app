<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The finder-only review shape: pairs the claimant's submitted answer with
 * the question text and the private expected_answer, so the report owner
 * can compare them side by side. Never served to the claimant themselves —
 * see ItemClaimController::answers() for the 403 guard.
 */
class ClaimAnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'question_id' => $this->verification_question_id,
            'question' => $this->verificationQuestion->question,
            'expected_answer' => $this->verificationQuestion->expected_answer,
            'answer' => $this->answer,
        ];
    }
}
