<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HelpOffer\StoreHelpOfferRequest;
use App\Http\Requests\HelpOffer\UpdateHelpOfferRequest;
use App\Http\Resources\HelpOfferResource;
use App\Models\HelpOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpOfferController extends Controller
{
    /**
     * Browse available help offers. Supports search + category filter for requesters.
     * GET /api/help-offers?search=&category_id=&status=
     */
    public function index(Request $request): JsonResponse
    {
        $query = HelpOffer::query()
            ->with(['helper', 'category'])
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($sub) => $sub->where('title', 'like', '%'.$request->string('search').'%')
                    ->orWhere('description', 'like', '%'.$request->string('search').'%')
            ))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
                fn ($q) => $q->available()
            )
            ->latest();

        $helpOffers = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => HelpOfferResource::collection($helpOffers->items()),
            'meta' => [
                'current_page' => $helpOffers->currentPage(),
                'last_page' => $helpOffers->lastPage(),
                'total' => $helpOffers->total(),
            ],
        ]);
    }

    /**
     * Help offers sorted nearest-first to the caller's GPS position, within
     * an optional radius. GET /api/help-offers/nearby?lat=&lng=&radius_km=&category_id=
     *
     * Uses the Haversine formula directly in SQL so sorting/filtering by
     * distance happens in the database rather than pulling every row into
     * PHP to compute it.
     */
    public function nearby(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1'],
        ]);

        $lat = $request->float('lat');
        $lng = $request->float('lng');
        $radiusKm = $request->float('radius_km', 25);

        // 6371 = Earth's radius in km.
        $distanceExpr = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * '
            .'cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

        $query = HelpOffer::query()
            ->select('*')
            ->selectRaw("{$distanceExpr} AS distance_km", [$lat, $lng, $lat])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->available()
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km')
            ->with(['helper', 'category']);

        $helpOffers = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => HelpOfferResource::collection($helpOffers->items()),
            'meta' => [
                'current_page' => $helpOffers->currentPage(),
                'last_page' => $helpOffers->lastPage(),
                'total' => $helpOffers->total(),
            ],
        ]);
    }

    /**
     * Helper's own help offers, any status. GET /api/my-help-offers
     */
    public function myHelpOffers(Request $request): JsonResponse
    {
        $helpOffers = $request->user()
            ->helpOffers()
            ->with(['category', 'assistanceRequests.requester'])
            ->latest()
            ->get();

        return response()->json(['data' => HelpOfferResource::collection($helpOffers)]);
    }

    public function store(StoreHelpOfferRequest $request): JsonResponse
    {
        $helpOffer = $request->user()->helpOffers()->create($request->validated());

        // create() doesn't reload column defaults set at the DB level (e.g.
        // status), so the in-memory model would otherwise report null for
        // them here even though the row itself is correct.
        $helpOffer->refresh()->load(['helper', 'category']);

        return response()->json([
            'message' => 'Help offer posted successfully.',
            'data' => new HelpOfferResource($helpOffer),
        ], 201);
    }

    public function show(HelpOffer $helpOffer): JsonResponse
    {
        $helpOffer->load(['helper', 'category', 'assistanceRequests.requester', 'assistanceRequests.ratings']);

        return response()->json(['data' => new HelpOfferResource($helpOffer)]);
    }

    public function update(UpdateHelpOfferRequest $request, HelpOffer $helpOffer): JsonResponse
    {
        $helpOffer->update($request->validated());

        return response()->json([
            'message' => 'Help offer updated successfully.',
            'data' => new HelpOfferResource($helpOffer->fresh(['helper', 'category'])),
        ]);
    }

    public function destroy(Request $request, HelpOffer $helpOffer): JsonResponse
    {
        if ($helpOffer->helper_id !== $request->user()->id) {
            abort(403, 'You can only delete your own help offers.');
        }

        $helpOffer->delete();

        return response()->json(['message' => 'Help offer deleted successfully.']);
    }
}
