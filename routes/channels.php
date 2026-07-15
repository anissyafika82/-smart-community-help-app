<?php

use App\Models\AssistanceRequest;
use App\Models\HelpOffer;
use Illuminate\Support\Facades\Broadcast;

/**
 * Authorizes a user to listen on chat.{helpOfferId}.{userIdA}.{userIdB} —
 * only the two participants themselves, and only if they're a legitimate
 * helper/requester pair for that help offer (helper, plus a requester who
 * has actually requested a portion of it).
 */
Broadcast::channel('chat.{helpOfferId}.{userIdA}.{userIdB}', function ($user, $helpOfferId, $userIdA, $userIdB) {
    if (! in_array($user->id, [(int) $userIdA, (int) $userIdB], true)) {
        return false;
    }

    $helpOffer = HelpOffer::find($helpOfferId);
    if (! $helpOffer) {
        return false;
    }

    $otherId = $user->id === (int) $userIdA ? (int) $userIdB : (int) $userIdA;

    $isHelperToRequester = $helpOffer->helper_id === $user->id
        && AssistanceRequest::where('help_offer_id', $helpOfferId)->where('requester_id', $otherId)->exists();

    $isRequesterToHelper = $helpOffer->helper_id === $otherId
        && AssistanceRequest::where('help_offer_id', $helpOfferId)->where('requester_id', $user->id)->exists();

    return $isHelperToRequester || $isRequesterToHelper;
});

/**
 * Authorizes a user to listen for live location updates on
 * tracking.{requestId} — only the helper assigned to that request and the
 * requester who made it.
 */
Broadcast::channel('tracking.{requestId}', function ($user, $requestId) {
    $assistanceRequest = AssistanceRequest::find($requestId);
    if (! $assistanceRequest) {
        return false;
    }

    return in_array($user->id, [$assistanceRequest->helper_id, $assistanceRequest->requester_id], true);
});
