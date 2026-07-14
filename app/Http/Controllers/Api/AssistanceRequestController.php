<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssistanceRequest\StoreAssistanceRequestRequest;
use App\Http\Resources\AssistanceRequestResource;
use App\Models\AssistanceRequest;
use App\Models\HelpOffer;
use App\Services\OneSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssistanceRequestController extends Controller
{
    public function __construct(private readonly OneSignalService $notifications)
    {
    }

    /**
     * Requester asks for some quantity of an available help offer. Multiple
     * requesters can request portions of the same offer until its stock
     * (quantity) runs out, at which point it flips to "claimed".
     * POST /api/help-offers/{helpOffer}/request
     */
    public function store(StoreAssistanceRequestRequest $request, HelpOffer $helpOffer): JsonResponse
    {
        $assistanceRequest = DB::transaction(function () use ($request, $helpOffer) {
            /** @var HelpOffer $locked */
            $locked = HelpOffer::whereKey($helpOffer->id)->lockForUpdate()->firstOrFail();

            $quantity = (int) $request->validated('quantity');

            if ($locked->status !== HelpOffer::STATUS_AVAILABLE || $locked->quantity < $quantity) {
                throw ValidationException::withMessages([
                    'help_offer' => 'This help offer no longer has enough availability.',
                ]);
            }

            $alreadyPending = AssistanceRequest::where('help_offer_id', $locked->id)
                ->where('requester_id', $request->user()->id)
                ->where('status', AssistanceRequest::STATUS_PENDING)
                ->exists();

            if ($alreadyPending) {
                throw ValidationException::withMessages([
                    'help_offer' => 'You already have a pending request on this help offer — wait for the helper to respond, or cancel it first.',
                ]);
            }

            $assistanceRequest = AssistanceRequest::create([
                'help_offer_id' => $locked->id,
                'requester_id' => $request->user()->id,
                'quantity' => $quantity,
                'priority' => $request->validated('priority') ?? AssistanceRequest::PRIORITY_MEDIUM,
                'notes' => $request->validated('notes'),
                'status' => AssistanceRequest::STATUS_PENDING,
            ]);

            $remaining = $locked->quantity - $quantity;
            $locked->update([
                'quantity' => $remaining,
                'status' => $remaining === 0 ? HelpOffer::STATUS_CLAIMED : HelpOffer::STATUS_AVAILABLE,
            ]);

            return $assistanceRequest;
        });

        $assistanceRequest->load(['helpOffer.helper', 'requester']);

        $this->notifications->notifyUser(
            $assistanceRequest->helpOffer->helper,
            'New request',
            "{$assistanceRequest->requester->name} requested your offer: {$assistanceRequest->helpOffer->title}",
            ['type' => 'new_request', 'help_offer_id' => $assistanceRequest->help_offer_id],
        );

        return response()->json([
            'message' => 'Request sent successfully. Awaiting helper confirmation.',
            'data' => new AssistanceRequestResource($assistanceRequest),
        ], 201);
    }

    /**
     * Requester's request history. GET /api/my-requests
     */
    public function myRequests(Request $request): JsonResponse
    {
        $requests = $request->user()
            ->assistanceRequests()
            ->with(['helpOffer.category', 'helpOffer.helper', 'ratings'])
            ->latest()
            ->get();

        return response()->json(['data' => AssistanceRequestResource::collection($requests)]);
    }

    /**
     * Helper approves a pending request on one of their help offers.
     * PATCH /api/requests/{assistanceRequest}/approve
     */
    public function approve(Request $request, AssistanceRequest $assistanceRequest): JsonResponse
    {
        $this->authorizeHelperAction($request, $assistanceRequest);

        $assistanceRequest->update(['status' => AssistanceRequest::STATUS_APPROVED, 'resolved_at' => now()]);
        $assistanceRequest->refresh()->load(['helpOffer', 'requester']);

        $this->notifications->notifyUser(
            $assistanceRequest->requester,
            'Request approved',
            "Your request for \"{$assistanceRequest->helpOffer->title}\" was approved.",
            ['type' => 'request_approved', 'assistance_request_id' => $assistanceRequest->id],
        );

        return response()->json([
            'message' => 'Request approved.',
            'data' => new AssistanceRequestResource($assistanceRequest),
        ]);
    }

    /**
     * Helper marks an approved request as "on the way" to the requester.
     * PATCH /api/requests/{assistanceRequest}/on-the-way
     */
    public function onTheWay(Request $request, AssistanceRequest $assistanceRequest): JsonResponse
    {
        $this->authorizeHelperAction($request, $assistanceRequest, AssistanceRequest::STATUS_APPROVED);

        $assistanceRequest->update(['status' => AssistanceRequest::STATUS_ON_THE_WAY]);
        $assistanceRequest->refresh()->load(['helpOffer', 'requester']);

        $this->notifications->notifyUser(
            $assistanceRequest->requester,
            'Helper is on the way',
            "Your helper is on the way for \"{$assistanceRequest->helpOffer->title}\".",
            ['type' => 'request_on_the_way', 'assistance_request_id' => $assistanceRequest->id],
        );

        return response()->json([
            'message' => 'Marked as on the way.',
            'data' => new AssistanceRequestResource($assistanceRequest),
        ]);
    }

    /**
     * Helper rejects a pending request; its quantity is returned to the
     * help offer's available stock.
     * PATCH /api/requests/{assistanceRequest}/reject
     */
    public function reject(Request $request, AssistanceRequest $assistanceRequest): JsonResponse
    {
        $this->authorizeHelperAction($request, $assistanceRequest);

        DB::transaction(function () use ($assistanceRequest) {
            $assistanceRequest->update(['status' => AssistanceRequest::STATUS_REJECTED, 'resolved_at' => now()]);
            $this->restoreQuantity($assistanceRequest);
        });
        $assistanceRequest->refresh()->load(['helpOffer', 'requester']);

        $this->notifications->notifyUser(
            $assistanceRequest->requester,
            'Request rejected',
            "Your request for \"{$assistanceRequest->helpOffer->title}\" was rejected.",
            ['type' => 'request_rejected', 'assistance_request_id' => $assistanceRequest->id],
        );

        return response()->json([
            'message' => 'Request rejected.',
            'data' => new AssistanceRequestResource($assistanceRequest),
        ]);
    }

    /**
     * Helper marks a request as completed (help delivered), optionally
     * attaching a proof-of-completion image URL (uploaded client-side to
     * Cloudinary, same pattern as help offer images). Stock was already
     * deducted when the request was made, so quantity is unchanged.
     * PATCH /api/requests/{assistanceRequest}/complete
     */
    public function complete(Request $request, AssistanceRequest $assistanceRequest): JsonResponse
    {
        $this->authorizeHelperAction($request, $assistanceRequest, AssistanceRequest::STATUS_ON_THE_WAY);

        $data = $request->validate(['proof_image_url' => ['nullable', 'url', 'max:2048']]);

        $assistanceRequest->update([
            'status' => AssistanceRequest::STATUS_COMPLETED,
            'proof_image_url' => $data['proof_image_url'] ?? null,
            'resolved_at' => now(),
        ]);
        $assistanceRequest->refresh()->load(['helpOffer', 'requester']);

        $this->notifications->notifyUser(
            $assistanceRequest->requester,
            'Marked as completed',
            "\"{$assistanceRequest->helpOffer->title}\" is marked completed. Don't forget to rate your helper!",
            ['type' => 'request_completed', 'assistance_request_id' => $assistanceRequest->id],
        );

        return response()->json([
            'message' => 'Request marked as completed.',
            'data' => new AssistanceRequestResource($assistanceRequest),
        ]);
    }

    /**
     * Requester cancels their own pending request; its quantity is returned
     * to the help offer's available stock.
     * PATCH /api/requests/{assistanceRequest}/cancel
     */
    public function cancel(Request $request, AssistanceRequest $assistanceRequest): JsonResponse
    {
        if ($assistanceRequest->requester_id !== $request->user()->id) {
            abort(403, 'You can only cancel your own requests.');
        }

        if ($assistanceRequest->status !== AssistanceRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'assistance_request' => 'Only pending requests can be cancelled.',
            ]);
        }

        DB::transaction(function () use ($assistanceRequest) {
            $assistanceRequest->update(['status' => AssistanceRequest::STATUS_CANCELLED, 'resolved_at' => now()]);
            $this->restoreQuantity($assistanceRequest);
        });

        return response()->json([
            'message' => 'Request cancelled.',
            'data' => new AssistanceRequestResource($assistanceRequest->fresh(['helpOffer', 'requester'])),
        ]);
    }

    /**
     * Return a request's quantity to its help offer's available stock,
     * flipping the offer back to "available" if it had run out.
     */
    private function restoreQuantity(AssistanceRequest $assistanceRequest): void
    {
        /** @var HelpOffer $helpOffer */
        $helpOffer = HelpOffer::whereKey($assistanceRequest->help_offer_id)->lockForUpdate()->firstOrFail();

        $helpOffer->update([
            'quantity' => $helpOffer->quantity + $assistanceRequest->quantity,
            'status' => $helpOffer->status === HelpOffer::STATUS_CLAIMED
                ? HelpOffer::STATUS_AVAILABLE
                : $helpOffer->status,
        ]);
    }

    private function authorizeHelperAction(
        Request $request,
        AssistanceRequest $assistanceRequest,
        string $requiredStatus = AssistanceRequest::STATUS_PENDING,
    ): void {
        $assistanceRequest->loadMissing('helpOffer');

        if ($assistanceRequest->helpOffer->helper_id !== $request->user()->id) {
            abort(403, 'You can only manage requests on your own help offers.');
        }

        if ($assistanceRequest->status !== $requiredStatus) {
            throw ValidationException::withMessages([
                'assistance_request' => "This request must be {$requiredStatus} for that action.",
            ]);
        }
    }
}
