<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VerificationQuestionResource;
use App\Models\ItemReport;
use Illuminate\Http\JsonResponse;

class VerificationQuestionController extends Controller
{
    /**
     * The questions a claimant must answer to claim this report — question
     * text only, never the expected_answer. Any authenticated user may
     * fetch these (needed to render the claim form); the private answers
     * stay behind ItemClaimController::answers() for the finder alone.
     * GET /api/item-reports/{itemReport}/verification-questions
     */
    public function forItemReport(ItemReport $itemReport): JsonResponse
    {
        $questions = $itemReport->verificationQuestions()->get();

        return response()->json(['data' => VerificationQuestionResource::collection($questions)]);
    }
}
