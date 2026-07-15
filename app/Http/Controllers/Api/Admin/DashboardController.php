<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemReportResource;
use App\Models\ItemClaim;
use App\Models\ItemReport;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Aggregate stats for the admin dashboard. GET /api/admin/dashboard
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'users' => [
                    'total' => User::count(),
                    'active' => User::where('is_active', true)->count(),
                ],
                'item_reports' => [
                    'total' => ItemReport::count(),
                    'lost' => ItemReport::where('report_type', ItemReport::TYPE_LOST)->count(),
                    'found' => ItemReport::where('report_type', ItemReport::TYPE_FOUND)->count(),
                    'returned' => ItemReport::where('status', ItemReport::STATUS_RETURNED)->count(),
                    'closed' => ItemReport::where('status', ItemReport::STATUS_CLOSED)->count(),
                ],
                'item_claims' => [
                    'total' => ItemClaim::count(),
                    'pending' => ItemClaim::where('status', ItemClaim::STATUS_PENDING)->count(),
                    'approved' => ItemClaim::where('status', ItemClaim::STATUS_APPROVED)->count(),
                ],
                'reports' => [
                    'total' => Report::count(),
                    'pending' => Report::where('status', Report::STATUS_PENDING)->count(),
                ],
                'recent_item_reports' => ItemReportResource::collection(
                    ItemReport::with(['user', 'category'])->latest()->limit(5)->get()
                ),
            ],
        ]);
    }
}
