<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemClaim;
use App\Models\ItemReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * The authenticated user's own dashboard stats. GET /api/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $reports = $user->itemReports();
        $claims = $user->claims();

        return response()->json([
            'data' => [
                'total_reports' => (clone $reports)->count(),
                'lost_reports' => (clone $reports)->where('report_type', ItemReport::TYPE_LOST)->count(),
                'found_reports' => (clone $reports)->where('report_type', ItemReport::TYPE_FOUND)->count(),
                'total_claims' => (clone $claims)->count(),
                'pending_claims' => (clone $claims)->where('status', ItemClaim::STATUS_PENDING)->count(),
                'items_returned' => $user->itemsReturnedCount(),
                'average_rating' => $user->averageRating(),
                'badge' => $user->badge(),
            ],
        ]);
    }
}
