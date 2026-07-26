<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemReportResource;
use App\Models\ItemReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemReportController extends Controller
{
    /**
     * List every item report in the system. GET /api/admin/item-reports?status=&report_type=
     */
    public function index(Request $request): JsonResponse
    {
        $itemReports = ItemReport::query()
            ->with(['user', 'category'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('report_type'), fn ($q) => $q->where('report_type', $request->string('report_type')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => ItemReportResource::collection($itemReports->items()),
            'meta' => [
                'current_page' => $itemReports->currentPage(),
                'last_page' => $itemReports->lastPage(),
                'total' => $itemReports->total(),
            ],
        ]);
    }

    /**
     * Admin can edit any item report (moderation) — the owner-only
     * UpdateItemReportRequest doesn't apply here since the admin usually
     * isn't the report's owner. PATCH /api/admin/item-reports/{itemReport}
     */
    public function update(Request $request, ItemReport $itemReport): JsonResponse
    {
        $fields = $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'item_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'location_name' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'in:lost,found,potential_match,claimed,verified,returned,closed'],
        ]);

        $itemReport->update($fields);

        return response()->json([
            'message' => 'Item report updated.',
            'data' => new ItemReportResource($itemReport->fresh(['user', 'category'])),
        ]);
    }

    /**
     * Admin can remove any item report (moderation).
     */
    public function destroy(ItemReport $itemReport): JsonResponse
    {
        $itemReport->delete();

        return response()->json(['message' => 'Item report removed successfully.']);
    }
}
