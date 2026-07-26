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

    /**
     * Override a claim's status directly (moderation) — bypasses the
     * normal approve/reject/return pipeline's ownership checks.
     * PATCH /api/admin/item-claims/{itemClaim}
     */
    public function update(Request $request, ItemClaim $itemClaim): JsonResponse
    {
        $fields = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $itemClaim->update($fields);

        return response()->json([
            'message' => 'Claim updated.',
            'data' => new ItemClaimResource($itemClaim->fresh(['itemReport.category', 'claimant'])),
        ]);
    }

    /**
     * Admin can remove any claim (moderation).
     * DELETE /api/admin/item-claims/{itemClaim}
     */
    public function destroy(ItemClaim $itemClaim): JsonResponse
    {
        $itemClaim->delete();

        return response()->json(['message' => 'Claim removed successfully.']);
    }
}
