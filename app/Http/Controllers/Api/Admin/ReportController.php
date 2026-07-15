<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * List all reports, optionally filtered by status. GET /api/admin/reports?status=
     */
    public function index(Request $request): JsonResponse
    {
        $reports = Report::query()
            ->with(['reporter', 'reportedUser', 'assistanceRequest.helpOffer'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => ReportResource::collection($reports->items()),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * Update a report's review status. PATCH /api/admin/reports/{report}
     */
    public function update(Request $request, Report $report): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,reviewed,dismissed,action_taken'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $report->update($data);

        return response()->json([
            'message' => 'Report updated.',
            'data' => new ReportResource($report->fresh(['reporter', 'reportedUser', 'assistanceRequest'])),
        ]);
    }
}
