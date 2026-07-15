<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssistanceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Requester dashboard stats. GET /api/dashboard/requester
     */
    public function requester(Request $request): JsonResponse
    {
        $requests = $request->user()->assistanceRequests();

        return response()->json([
            'data' => [
                'total_requests' => (clone $requests)->count(),
                'pending_requests' => (clone $requests)->where('status', AssistanceRequest::STATUS_PENDING)->count(),
                'completed_requests' => (clone $requests)->where('status', AssistanceRequest::STATUS_COMPLETED)->count(),
            ],
        ]);
    }

    /**
     * Volunteer (helper) dashboard stats. GET /api/dashboard/volunteer
     */
    public function volunteer(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'total_helps' => $user->completedHelpsCount(),
                'average_rating' => $user->averageRating(),
                'badge' => $user->badge(),
            ],
        ]);
    }
}
