<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RatingResource;
use App\Models\Activity;
use App\Models\AssistanceRequest;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RatingController extends Controller
{
    /**
     * Ratings the authenticated user has received, newest first, with who
     * gave each one. GET /api/ratings/received
     */
    public function received(Request $request): JsonResponse
    {
        $ratings = $request->user()
            ->ratingsReceived()
            ->with(['ratedBy', 'assistanceRequest.helpOffer'])
            ->latest()
            ->get();

        return response()->json(['data' => RatingResource::collection($ratings)]);
    }

    /**
     * Either party (helper or requester) rates the other after a completed
     * request — one rating per person, per request.
     * POST /api/requests/{assistanceRequest}/rating
     */
    public function store(Request $request, AssistanceRequest $assistanceRequest): JsonResponse
    {
        $assistanceRequest->loadMissing('helpOffer');
        $me = $request->user();

        $helperId = $assistanceRequest->help_offer_id !== null
            ? $assistanceRequest->helpOffer->helper_id
            : $assistanceRequest->helper_id;
        $requesterId = $assistanceRequest->requester_id;

        if (! in_array($me->id, [$helperId, $requesterId], true)) {
            abort(403, 'You are not part of this request.');
        }

        if ($assistanceRequest->status !== AssistanceRequest::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'assistance_request' => 'You can only rate a request after it has been completed.',
            ]);
        }

        $alreadyRated = Rating::where('assistance_request_id', $assistanceRequest->id)
            ->where('rated_by_user_id', $me->id)
            ->exists();

        if ($alreadyRated) {
            throw ValidationException::withMessages([
                'assistance_request' => 'You have already rated this request.',
            ]);
        }

        $data = $request->validate([
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $ratedUserId = $me->id === $helperId ? $requesterId : $helperId;

        $rating = Rating::create([
            'assistance_request_id' => $assistanceRequest->id,
            'rated_by_user_id' => $me->id,
            'rated_user_id' => $ratedUserId,
            'stars' => $data['stars'],
            'comment' => $data['comment'] ?? null,
        ]);

        Activity::log(
            $me->id,
            Activity::TYPE_RATING_SUBMITTED,
            "Rated {$rating->ratedUser->name} {$data['stars']} star(s)",
            $assistanceRequest->id,
        );

        return response()->json([
            'message' => 'Rating submitted successfully.',
            'data' => new RatingResource($rating->load(['ratedBy', 'ratedUser'])),
        ], 201);
    }
}
