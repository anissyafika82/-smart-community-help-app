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
     * List every request in the system.
     * GET /api/admin/requests?status=&category_id=&priority=&is_sos=
     */
    public function index(Request $request): JsonResponse
    {
        $requests = AssistanceRequest::query()
            ->with(['helpOffer.category', 'category', 'requester', 'helper'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->when($request->filled('is_sos'), fn ($q) => $q->where('is_sos', $request->boolean('is_sos')))
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
