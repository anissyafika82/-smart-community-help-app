<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    /**
     * File a report against a claim or a user. POST /api/reports
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reported_user_id' => $request->validated('reported_user_id'),
            'item_claim_id' => $request->validated('item_claim_id'),
            'reason' => $request->validated('reason'),
            'description' => $request->validated('description'),
            'status' => Report::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => 'Report submitted. Our team will review it shortly.',
            'data' => new ReportResource($report),
        ], 201);
    }
}
