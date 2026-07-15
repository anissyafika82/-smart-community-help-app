<?php

use App\Models\ItemClaim;
use App\Models\ItemReport;
use Illuminate\Support\Facades\Broadcast;

/**
 * Authorizes a user to listen on chat.{itemReportId}.{userIdA}.{userIdB} —
 * only the two participants themselves, and only if they're a legitimate
 * report-owner/claimant pair for that item report (owner, plus a claimant
 * who has actually submitted a claim against it).
 */
Broadcast::channel('chat.{itemReportId}.{userIdA}.{userIdB}', function ($user, $itemReportId, $userIdA, $userIdB) {
    if (! in_array($user->id, [(int) $userIdA, (int) $userIdB], true)) {
        return false;
    }

    $itemReport = ItemReport::find($itemReportId);
    if (! $itemReport) {
        return false;
    }

    $otherId = $user->id === (int) $userIdA ? (int) $userIdB : (int) $userIdA;

    $isOwnerToClaimant = $itemReport->user_id === $user->id
        && ItemClaim::where('item_report_id', $itemReportId)->where('claimant_id', $otherId)->exists();

    $isClaimantToOwner = $itemReport->user_id === $otherId
        && ItemClaim::where('item_report_id', $itemReportId)->where('claimant_id', $user->id)->exists();

    return $isOwnerToClaimant || $isClaimantToOwner;
});
