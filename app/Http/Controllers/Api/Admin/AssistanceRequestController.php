<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssistanceRequestResource;
use App\Models\AssistanceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistanceRequestController extends Controller
{
    /**
     * List every request in the system. GET /api/admin/requests?status=
     */
    public function index(Request $request): JsonResponse
    {
        $requests = AssistanceRequest::query()
            ->with(['helpOffer.category', 'requester'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => AssistanceRequestResource::collection($requests->items()),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
            ],
        ]);
    }
}
