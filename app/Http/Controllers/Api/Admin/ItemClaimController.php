<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemClaimResource;
use App\Models\ItemClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemClaimController extends Controller
{
    /**
     * List every claim in the system. GET /api/admin/item-claims?status=
     */
    public function index(Request $request): JsonResponse
    {
        $claims = ItemClaim::query()
            ->with(['itemReport.category', 'claimant'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => ItemClaimResource::collection($claims->items()),
            'meta' => [
                'current_page' => $claims->currentPage(),
                'last_page' => $claims->lastPage(),
                'total' => $claims->total(),
            ],
        ]);
    }
}
