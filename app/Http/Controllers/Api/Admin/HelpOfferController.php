<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\HelpOfferResource;
use App\Models\HelpOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpOfferController extends Controller
{
    /**
     * List every help offer in the system. GET /api/admin/help-offers?status=
     */
    public function index(Request $request): JsonResponse
    {
        $helpOffers = HelpOffer::query()
            ->with(['helper', 'category'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

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
     * Admin can remove any help offer (moderation).
     */
    public function destroy(HelpOffer $helpOffer): JsonResponse
    {
        $helpOffer->delete();

        return response()->json(['message' => 'Help offer removed successfully.']);
    }
}
