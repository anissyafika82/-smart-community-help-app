<?php

namespace App\Http\Controllers\Api;

use App\Events\HelperLocationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssistanceRequest\StoreAssistanceRequestRequest;
use App\Http\Requests\AssistanceRequest\StoreSosRequestRequest;
use App\Http\Resources\AssistanceRequestResource;
use App\Models\Activity;
use App\Models\AssistanceRequest;
use App\Models\Category;
use App\Models\HelpOffer;
use App\Models\User;
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
                'category_id' => $locked->category_id,
                'requester_id' => $request->user()->id,
                'quantity' => $quantity,
                'priority' => $request->validated('priority') ?? AssistanceRequest::PRIORITY_MEDIUM,
                'notes' => $request->validated('notes'),
                'scheduled_at' => $request->validated('scheduled_at'),
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

        Activity::log(
            $assistanceRequest->requester_id,
            Activity::TYPE_REQUEST_CREATED,
            "Requested \"{$assistanceRequest->helpOffer->title}\"",
            $assistanceRequest->id,
        );

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
     * Requester raises an emergency SOS request directly, with no
     * pre-existing help offer — any nearby volunteer can pick it up.
     * Always high-urgency (priority is forced to "emergency") and pushed
     * to every active volunteer immediately.
     * POST /api/requests/sos
     */
    public function storeSos(StoreSosRequestRequest $request): JsonResponse
    {
        $categoryId = $request->validated('category_id')
            ?? Category::where('slug', 'emergency-help')->value('id');

        $assistanceRequest = AssistanceRequest::create([
            'requester_id' => $request->user()->id,
            'category_id' => $categoryId,
            'quantity' => 1,
            'priority' => AssistanceRequest::PRIORITY_EMERGENCY,
            'is_sos' => true,
            'latitude' => $request->validated('latitude'),
            'longitude' => $request->validated('longitude'),
            'address' => $request->validated('address'),
            'notes' => $request->validated('notes'),
            'status' => AssistanceRequest::STATUS_PENDING,
        ]);
        $assistanceRequest->load(['requester', 'category']);

        Activity::log(
            $assistanceRequest->requester_id,
            Activity::TYPE_SOS_CREATED,
            'Raised an emergency SOS request',
            $assistanceRequest->id,
        );

        // No stored "home" location for volunteers to filter by, so an SOS
        // alert pushes to every active volunteer rather than a geo-filtered
        // subset — see /api/requests/sos/nearby for the geo-filtered browse
        // volunteers use once they open the app.
        User::where('role', User::ROLE_HELPER)
            ->where('is_active', true)
            ->whereNotNull('onesignal_player_id')
            ->get()
            ->each(fn (User $helper) => $this->notifications->notifyUser(
                $helper,
                'Emergency SOS nearby',
                "{$assistanceRequest->requester->name} needs urgent help.",
                ['type' => 'sos_created', 'assistance_request_id' => $assistanceRequest->id],
            ));

        return response()->json([
            'message' => 'SOS request sent. Nearby volunteers have been notified.',
            'data' => new AssistanceRequestResource($assistanceRequest),
        ], 201);
    }

    /**
     * Open SOS requests (not yet picked up), nearest-first, for volunteers
     * to browse. GET /api/requests/sos/nearby?lat=&lng=&radius_km=&category_id=&priority=
     */
    public function nearbySos(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1'],
        ]);

        $lat = $request->float('lat');
        $lng = $request->float('lng');
        $radiusKm = $request->float('radius_km', 25);

        $distanceExpr = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * '
            .'cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

        $requests = AssistanceRequest::query()
            ->select('*')
            ->selectRaw("{$distanceExpr} AS distance_km", [$lat, $lng, $lat])
            ->where('is_sos', true)
            ->where('status', AssistanceRequest::STATUS_PENDING)
            ->whereNull('helper_id')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km')
            ->with(['requester', 'category'])
            ->get();

        return response()->json(['data' => AssistanceRequestResource::collection($requests)]);
    }

    /**
     * Requester's own upcoming scheduled requests (future-dated, not yet
     * resolved). GET /api/requests/scheduled
     */
    public function scheduled(Request $request): JsonResponse
    {
        $requests = $request->user()
            ->assistanceRequests()
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->whereIn('status', [AssistanceRequest::STATUS_PENDING, AssistanceRequest::STATUS_APPROVED])
            ->with(['helpOffer.category', 'helpOffer.helper'])
            ->orderBy('scheduled_at')
            ->get();

        return response()->json(['data' => AssistanceRequestResource::collection($requests)]);
    }

    /**
     * Requester's request history, with optional filters.
     * GET /api/my-requests?category_id=&priority=&status=
     */
    public function myRequests(Request $request): JsonResponse
    {
        $requests = $request->user()
            ->assistanceRequests()
            ->with(['helpOffer.category', 'helpOffer.helper', 'helper', 'category', 'ratings'])
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
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

        $assistanceRequest->update([
            'status' => AssistanceRequest::STATUS_APPROVED,
            'helper_id' => $assistanceRequest->helpOffer->helper_id,
            'resolved_at' => now(),
        ]);
        $assistanceRequest->refresh()->load(['helpOffer', 'requester']);

        Activity::log(
            $assistanceRequest->helper_id,
            Activity::TYPE_REQUEST_ACCEPTED,
            "Accepted request for \"{$assistanceRequest->helpOffer->title}\"",
            $assistanceRequest->id,
        );

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
     * Volunteer picks up an open SOS request (first to accept gets it).
     * PATCH /api/requests/{assistanceRequest}/accept-sos
     */
    public function acceptSos(Request $request, AssistanceRequest $assistanceRequest): JsonResponse
    {
        if (! $assistanceRequest->is_sos || $assistanceRequest->help_offer_id !== null) {
            abort(422, 'This action only applies to SOS requests.');
        }

        $updated = DB::transaction(function () use ($request, $assistanceRequest) {
            /** @var AssistanceRequest $locked */
            $locked = AssistanceRequest::whereKey($assistanceRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== AssistanceRequest::STATUS_PENDING || $locked->helper_id !== null) {
                throw ValidationException::withMessages([
                    'assistance_request' => 'This SOS request has already been picked up by another volunteer.',
                ]);
            }

            $locked->update([
                'status' => AssistanceRequest::STATUS_APPROVED,
                'helper_id' => $request->user()->id,
                'resolved_at' => now(),
            ]);

            return $locked;
        });
        $updated->refresh()->load(['helper', 'requester']);

        Activity::log(
            $updated->helper_id,
            Activity::TYPE_REQUEST_ACCEPTED,
            'Accepted an emergency SOS request',
            $updated->id,
        );

        $this->notifications->notifyUser(
            $updated->requester,
            'Volunteer found',
            "{$updated->helper->name} is responding to your SOS request.",
            ['type' => 'sos_accepted', 'assistance_request_id' => $updated->id],
        );

        return response()->json([
            'message' => 'SOS request accepted.',
            'data' => new AssistanceRequestResource($updated),
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

        Activity::log(
            $assistanceRequest->helper_id,
            Activity::TYPE_REQUEST_ON_THE_WAY,
            'Marked a request as on the way',
            $assistanceRequest->id,
        );

        $this->notifications->notifyUser(
            $assistanceRequest->requester,
            'Helper is on the way',
            'Your helper is on the way'.($assistanceRequest->helpOffer ? " for \"{$assistanceRequest->helpOffer->title}\"." : '.'),
            ['type' => 'request_on_the_way', 'assistance_request_id' => $assistanceRequest->id],
        );

        return response()->json([
            'message' => 'Marked as on the way.',
            'data' => new AssistanceRequestResource($assistanceRequest),
        ]);
    }

    /**
     * Helper updates their live GPS position while en route (approved or
     * on-the-way), broadcast instantly to the requester's map.
     * PATCH /api/requests/{assistanceRequest}/location
     */
    public function updateHelperLocation(Request $request, AssistanceRequest $assistanceRequest): JsonResponse
    {
        if ($assistanceRequest->helper_id !== $request->user()->id) {
            abort(403, 'You are not the assigned helper for this request.');
        }

        if (! in_array($assistanceRequest->status, [AssistanceRequest::STATUS_APPROVED, AssistanceRequest::STATUS_ON_THE_WAY], true)) {
            throw ValidationException::withMessages([
                'assistance_request' => 'Location updates only apply while a request is approved or on the way.',
            ]);
        }

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $assistanceRequest->update([
            'helper_latitude' => $data['latitude'],
            'helper_longitude' => $data['longitude'],
            'helper_location_updated_at' => now(),
        ]);

        broadcast(new HelperLocationUpdated($assistanceRequest));

        return response()->json(['message' => 'Location updated.']);
    }

    /**
     * Helper rejects a pending request; its quantity is returned to the
     * help offer's available stock (if any — SOS requests have none).
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
            'Your request was rejected'.($assistanceRequest->helpOffer ? " for \"{$assistanceRequest->helpOffer->title}\"." : '.'),
            ['type' => 'request_rejected', 'assistance_request_id' => $assistanceRequest->id],
        );

        return response()->json([
            'message' => 'Request rejected.',
            'data' => new AssistanceRequestResource($assistanceRequest),
        ]);
    }

    /**
     * Helper marks a request delivered, attaching a proof-of-completion
     * image (uploaded client-side to Cloudinary). This does NOT finalize
     * the request — it moves to "pending_confirmation" so the requester
     * can view the proof and confirm before it's marked completed.
     * PATCH /api/requests/{assistanceRequest}/complete
     */
    public function complete(Request $request, AssistanceRequest $assistanceRequest): JsonResponse
    {
        $this->authorizeHelperAction($request, $assistanceRequest, AssistanceRequest::STATUS_ON_THE_WAY);

        $data = $request->validate(['proof_image_url' => ['nullable', 'url', 'max:2048']]);

        $assistanceRequest->update([
            'status' => AssistanceRequest::STATUS_PENDING_CONFIRMATION,
            'proof_image_url' => $data['proof_image_url'] ?? null,
            'resolved_at' => now(),
        ]);
        $assistanceRequest->refresh()->load(['helpOffer', 'requester']);

        $this->notifications->notifyUser(
            $assistanceRequest->requester,
            'Awaiting your confirmation',
            'Your helper marked the request as delivered — check the proof photo and confirm completion.',
            ['type' => 'request_pending_confirmation', 'assistance_request_id' => $assistanceRequest->id],
        );

        return response()->json([
            'message' => 'Marked as delivered. Waiting for requester confirmation.',
            'data' => new AssistanceRequestResource($assistanceRequest),
        ]);
    }

    /**
     * Requester confirms completion after reviewing the helper's proof
     * photo — the only action that finalizes a request as "completed".
     * PATCH /api/requests/{assistanceRequest}/confirm
     */
    public function confirmCompletion(Request $request, AssistanceRequest $assistanceRequest): JsonResponse
    {
        if ($assistanceRequest->requester_id !== $request->user()->id) {
            abort(403, 'You can only confirm your own requests.');
        }

        if ($assistanceRequest->status !== AssistanceRequest::STATUS_PENDING_CONFIRMATION) {
            throw ValidationException::withMessages([
                'assistance_request' => 'This request is not awaiting confirmation.',
            ]);
        }

        $assistanceRequest->update([
            'status' => AssistanceRequest::STATUS_COMPLETED,
            'confirmed_at' => now(),
        ]);
        $assistanceRequest->refresh()->load(['helpOffer', 'helper', 'requester']);

        Activity::log(
            $assistanceRequest->requester_id,
            Activity::TYPE_REQUEST_COMPLETED,
            'Confirmed a request as completed',
            $assistanceRequest->id,
        );

        if ($assistanceRequest->helper) {
            $this->notifications->notifyUser(
                $assistanceRequest->helper,
                'Completion confirmed',
                'The requester confirmed completion. Thanks for helping — a rating may be waiting!',
                ['type' => 'request_confirmed', 'assistance_request_id' => $assistanceRequest->id],
            );
        }

        return response()->json([
            'message' => 'Completion confirmed.',
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
     * flipping the offer back to "available" if it had run out. SOS
     * requests have no help offer, so this is a no-op for them.
     */
    private function restoreQuantity(AssistanceRequest $assistanceRequest): void
    {
        if ($assistanceRequest->help_offer_id === null) {
            return;
        }

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

        $responsibleHelperId = $assistanceRequest->help_offer_id !== null
            ? $assistanceRequest->helpOffer->helper_id
            : $assistanceRequest->helper_id;

        if ($responsibleHelperId !== $request->user()->id) {
            abort(403, 'You can only manage requests assigned to you.');
        }

        if ($assistanceRequest->status !== $requiredStatus) {
            throw ValidationException::withMessages([
                'assistance_request' => "This request must be {$requiredStatus} for that action.",
            ]);
        }
    }
}
