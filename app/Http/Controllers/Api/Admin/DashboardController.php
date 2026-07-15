<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\HelpOfferResource;
use App\Models\AssistanceRequest;
use App\Models\HelpOffer;
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
                    'helpers' => User::where('role', User::ROLE_HELPER)->count(),
                    'requesters' => User::where('role', User::ROLE_REQUESTER)->count(),
                    'active' => User::where('is_active', true)->count(),
                ],
                'help_offers' => [
                    'total' => HelpOffer::count(),
                    'available' => HelpOffer::where('status', HelpOffer::STATUS_AVAILABLE)->count(),
                    'claimed' => HelpOffer::where('status', HelpOffer::STATUS_CLAIMED)->count(),
                    'completed' => HelpOffer::where('status', HelpOffer::STATUS_COMPLETED)->count(),
                    'expired' => HelpOffer::where('status', HelpOffer::STATUS_EXPIRED)->count(),
                ],
                'assistance_requests' => [
                    'total' => AssistanceRequest::count(),
                    'pending' => AssistanceRequest::where('status', AssistanceRequest::STATUS_PENDING)->count(),
                    'approved' => AssistanceRequest::where('status', AssistanceRequest::STATUS_APPROVED)->count(),
                    'completed' => AssistanceRequest::where('status', AssistanceRequest::STATUS_COMPLETED)->count(),
                    'emergency' => AssistanceRequest::where('is_sos', true)->count(),
                ],
                'reports' => [
                    'total' => Report::count(),
                    'pending' => Report::where('status', Report::STATUS_PENDING)->count(),
                ],
                'recent_help_offers' => HelpOfferResource::collection(
                    HelpOffer::with(['helper', 'category'])->latest()->limit(5)->get()
                ),
            ],
        ]);
    }
}
