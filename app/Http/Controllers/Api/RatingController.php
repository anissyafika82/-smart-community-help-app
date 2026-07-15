<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RatingResource;
use App\Models\Activity;
use App\Models\ItemClaim;
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
            ->with(['ratedBy', 'itemClaim.itemReport'])
            ->latest()
            ->get();

        return response()->json(['data' => RatingResource::collection($ratings)]);
    }

    /**
     * Either party (report owner or claimant) rates the other after an
     * item has been returned — one rating per person, per claim.
     * POST /api/claims/{itemClaim}/rating
     */
    public function store(Request $request, ItemClaim $itemClaim): JsonResponse
    {
        $itemClaim->loadMissing('itemReport');
        $me = $request->user();

        $ownerId = $itemClaim->itemReport->user_id;
        $claimantId = $itemClaim->claimant_id;

        if (! in_array($me->id, [$ownerId, $claimantId], true)) {
            abort(403, 'You are not part of this claim.');
        }

        if (! $itemClaim->isReturned()) {
            throw ValidationException::withMessages([
                'item_claim' => 'You can only rate a claim after the item has been returned.',
            ]);
        }

        $alreadyRated = Rating::where('item_claim_id', $itemClaim->id)
            ->where('rated_by_user_id', $me->id)
            ->exists();

        if ($alreadyRated) {
            throw ValidationException::withMessages([
                'item_claim' => 'You have already rated this claim.',
            ]);
        }

        $data = $request->validate([
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $ratedUserId = $me->id === $ownerId ? $claimantId : $ownerId;

        $rating = Rating::create([
            'item_claim_id' => $itemClaim->id,
            'rated_by_user_id' => $me->id,
            'rated_user_id' => $ratedUserId,
            'stars' => $data['stars'],
            'comment' => $data['comment'] ?? null,
        ]);

        Activity::log(
            $me->id,
            Activity::TYPE_RATING_SUBMITTED,
            "Rated {$rating->ratedUser->name} {$data['stars']} star(s)",
            $itemClaim->id,
        );

        return response()->json([
            'message' => 'Rating submitted successfully.',
            'data' => new RatingResource($rating->load(['ratedBy', 'ratedUser'])),
        ], 201);
    }
}
