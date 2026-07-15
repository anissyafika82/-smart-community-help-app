<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemClaim\StoreItemClaimRequest;
use App\Http\Resources\ItemClaimResource;
use App\Models\Activity;
use App\Models\ItemClaim;
use App\Models\ItemReport;
use App\Services\OneSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemClaimController extends Controller
{
    public function __construct(private readonly OneSignalService $notifications)
    {
    }

    /**
     * Submit an ownership claim against an item report. Multiple people
     * can claim the same report until the owner verifies one.
     * POST /api/item-reports/{itemReport}/claims
     */
    public function store(StoreItemClaimRequest $request, ItemReport $itemReport): JsonResponse
    {
        if ($itemReport->user_id === $request->user()->id) {
            throw ValidationException::withMessages([
                'item_report' => 'You cannot claim your own report.',
            ]);
        }

        $itemClaim = DB::transaction(function () use ($request, $itemReport) {
            /** @var ItemReport $locked */
            $locked = ItemReport::whereKey($itemReport->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [ItemReport::STATUS_LOST, ItemReport::STATUS_FOUND, ItemReport::STATUS_POTENTIAL_MATCH], true)) {
                throw ValidationException::withMessages([
                    'item_report' => 'This report is no longer open for claims.',
                ]);
            }

            $alreadyPending = ItemClaim::where('item_report_id', $locked->id)
                ->where('claimant_id', $request->user()->id)
                ->where('status', ItemClaim::STATUS_PENDING)
                ->exists();

            if ($alreadyPending) {
                throw ValidationException::withMessages([
                    'item_report' => 'You already have a pending claim on this report — wait for a response, or cancel it first.',
                ]);
            }

            $itemClaim = ItemClaim::create([
                'item_report_id' => $locked->id,
                'claimant_id' => $request->user()->id,
                'claim_message' => $request->validated('claim_message'),
                'proof_description' => $request->validated('proof_description'),
                'proof_image_url' => $request->validated('proof_image_url'),
                'status' => ItemClaim::STATUS_PENDING,
            ]);

            $locked->update(['status' => ItemReport::STATUS_CLAIMED]);

            return $itemClaim;
        });

        $itemClaim->load(['itemReport.user', 'claimant']);

        Activity::log(
            $itemClaim->claimant_id,
            Activity::TYPE_CLAIM_SUBMITTED,
            "Submitted a claim on \"{$itemClaim->itemReport->item_name}\"",
            $itemClaim->id,
        );

        $this->notifications->notifyUser(
            $itemClaim->itemReport->user,
            'New claim on your report',
            "{$itemClaim->claimant->name} submitted a claim on \"{$itemClaim->itemReport->item_name}\".",
            ['type' => 'new_claim', 'item_report_id' => $itemClaim->item_report_id],
        );

        return response()->json([
            'message' => 'Claim submitted successfully. Awaiting verification.',
            'data' => new ItemClaimResource($itemClaim),
        ], 201);
    }

    /**
     * The authenticated user's own claims, with optional filters.
     * GET /api/my-claims?status=
     */
    public function myClaims(Request $request): JsonResponse
    {
        $claims = $request->user()
            ->claims()
            ->with(['itemReport.category', 'itemReport.user', 'ratings'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->get();

        return response()->json(['data' => ItemClaimResource::collection($claims)]);
    }

    /**
     * Item report owner verifies (approves) a claim — the other pending
     * claims on the same report are automatically rejected.
     * PATCH /api/claims/{itemClaim}/approve
     */
    public function approve(Request $request, ItemClaim $itemClaim): JsonResponse
    {
        $this->authorizeOwnerAction($request, $itemClaim, ItemClaim::STATUS_PENDING);

        DB::transaction(function () use ($itemClaim) {
            $itemClaim->update(['status' => ItemClaim::STATUS_APPROVED, 'verified_at' => now()]);

            ItemClaim::where('item_report_id', $itemClaim->item_report_id)
                ->where('id', '!=', $itemClaim->id)
                ->where('status', ItemClaim::STATUS_PENDING)
                ->update(['status' => ItemClaim::STATUS_REJECTED]);

            $itemClaim->itemReport()->update(['status' => ItemReport::STATUS_VERIFIED]);
        });
        $itemClaim->refresh()->load(['itemReport', 'claimant']);

        Activity::log(
            $itemClaim->itemReport->user_id,
            Activity::TYPE_CLAIM_VERIFIED,
            "Verified {$itemClaim->claimant->name}'s claim on \"{$itemClaim->itemReport->item_name}\"",
            $itemClaim->id,
        );

        $this->notifications->notifyUser(
            $itemClaim->claimant,
            'Claim verified',
            "Your claim on \"{$itemClaim->itemReport->item_name}\" was verified. Arrange the handover!",
            ['type' => 'claim_verified', 'item_claim_id' => $itemClaim->id],
        );

        return response()->json([
            'message' => 'Claim verified.',
            'data' => new ItemClaimResource($itemClaim),
        ]);
    }

    /**
     * Item report owner rejects a claim; the report reverts to its
     * original lost/found status if no other active claims remain.
     * PATCH /api/claims/{itemClaim}/reject
     */
    public function reject(Request $request, ItemClaim $itemClaim): JsonResponse
    {
        $this->authorizeOwnerAction($request, $itemClaim, ItemClaim::STATUS_PENDING);

        DB::transaction(function () use ($itemClaim) {
            $itemClaim->update(['status' => ItemClaim::STATUS_REJECTED]);
            $this->revertStatusIfNoActiveClaims($itemClaim->itemReport);
        });
        $itemClaim->refresh()->load(['itemReport', 'claimant']);

        $this->notifications->notifyUser(
            $itemClaim->claimant,
            'Claim rejected',
            "Your claim on \"{$itemClaim->itemReport->item_name}\" was rejected.",
            ['type' => 'claim_rejected', 'item_claim_id' => $itemClaim->id],
        );

        return response()->json([
            'message' => 'Claim rejected.',
            'data' => new ItemClaimResource($itemClaim),
        ]);
    }

    /**
     * Mark an item as returned — the final step, only possible once a
     * claim has been verified. Either the report owner or the claimant
     * can confirm the handover happened.
     * PATCH /api/claims/{itemClaim}/return
     */
    public function markReturned(Request $request, ItemClaim $itemClaim): JsonResponse
    {
        $itemClaim->loadMissing('itemReport');
        $me = $request->user();

        if (! in_array($me->id, [$itemClaim->itemReport->user_id, $itemClaim->claimant_id], true)) {
            abort(403, 'You are not part of this claim.');
        }

        if ($itemClaim->status !== ItemClaim::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'item_claim' => 'Only a verified claim can be marked as returned.',
            ]);
        }

        DB::transaction(function () use ($itemClaim) {
            $itemClaim->update(['returned_at' => now()]);
            $itemClaim->itemReport()->update(['status' => ItemReport::STATUS_RETURNED]);
        });
        $itemClaim->refresh()->load(['itemReport', 'claimant']);

        Activity::log(
            $me->id,
            Activity::TYPE_ITEM_RETURNED,
            "Marked \"{$itemClaim->itemReport->item_name}\" as returned",
            $itemClaim->id,
        );

        $otherPartyId = $me->id === $itemClaim->itemReport->user_id ? $itemClaim->claimant_id : $itemClaim->itemReport->user_id;
        $this->notifications->notifyUser(
            $itemClaim->itemReport->user_id === $otherPartyId ? $itemClaim->itemReport->user : $itemClaim->claimant,
            'Item returned',
            "\"{$itemClaim->itemReport->item_name}\" is marked as returned. Don't forget to rate!",
            ['type' => 'item_returned', 'item_claim_id' => $itemClaim->id],
        );

        return response()->json([
            'message' => 'Item marked as returned.',
            'data' => new ItemClaimResource($itemClaim),
        ]);
    }

    /**
     * Claimant withdraws their own pending claim; the report reverts to
     * its original lost/found status if no other active claims remain.
     * PATCH /api/claims/{itemClaim}/cancel
     */
    public function cancel(Request $request, ItemClaim $itemClaim): JsonResponse
    {
        if ($itemClaim->claimant_id !== $request->user()->id) {
            abort(403, 'You can only cancel your own claims.');
        }

        if ($itemClaim->status !== ItemClaim::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'item_claim' => 'Only pending claims can be cancelled.',
            ]);
        }

        DB::transaction(function () use ($itemClaim) {
            $itemClaim->update(['status' => ItemClaim::STATUS_REJECTED]);
            $this->revertStatusIfNoActiveClaims($itemClaim->itemReport);
        });

        return response()->json([
            'message' => 'Claim cancelled.',
            'data' => new ItemClaimResource($itemClaim->fresh(['itemReport', 'claimant'])),
        ]);
    }

    /**
     * If an item report has no more pending/approved claims, revert it
     * back to its original lost/found status so it's open for new claims.
     */
    private function revertStatusIfNoActiveClaims(ItemReport $itemReport): void
    {
        /** @var ItemReport $locked */
        $locked = ItemReport::whereKey($itemReport->id)->lockForUpdate()->firstOrFail();

        $stillActive = $locked->activeClaims()->exists();

        if (! $stillActive && in_array($locked->status, [ItemReport::STATUS_CLAIMED, ItemReport::STATUS_POTENTIAL_MATCH], true)) {
            $locked->update(['status' => $locked->report_type]);
        }
    }

    private function authorizeOwnerAction(Request $request, ItemClaim $itemClaim, string $requiredStatus): void
    {
        $itemClaim->loadMissing('itemReport');

        if ($itemClaim->itemReport->user_id !== $request->user()->id) {
            abort(403, 'You can only manage claims on your own reports.');
        }

        if ($itemClaim->status !== $requiredStatus) {
            throw ValidationException::withMessages([
                'item_claim' => "This claim must be {$requiredStatus} for that action.",
            ]);
        }
    }
}
