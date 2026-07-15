<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * The authenticated user's own activity timeline, newest first.
     * GET /api/activities
     */
    public function index(Request $request): JsonResponse
    {
        $activities = $request->user()
            ->activities()
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => ActivityResource::collection($activities)]);
    }
}
