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
     * Admin can remove any item report (moderation).
     */
    public function destroy(ItemReport $itemReport): JsonResponse
    {
        $itemReport->delete();

        return response()->json(['message' => 'Item report removed successfully.']);
    }
}
