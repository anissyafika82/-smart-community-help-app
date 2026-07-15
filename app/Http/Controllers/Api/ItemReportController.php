<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemReport\StoreItemReportRequest;
use App\Http\Requests\ItemReport\UpdateItemReportRequest;
use App\Http\Resources\ItemReportResource;
use App\Models\ItemReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemReportController extends Controller
{
    /**
     * Browse lost & found items. Supports keyword search, category,
     * report type, and date-range filters.
     * GET /api/item-reports?search=&category_id=&report_type=&date_from=&date_to=&status=
     */
    public function index(Request $request): JsonResponse
    {
        $query = ItemReport::query()
            ->with(['user', 'category'])
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($sub) => $sub->where('item_name', 'like', '%'.$request->string('search').'%')
                    ->orWhere('description', 'like', '%'.$request->string('search').'%')
            ))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('report_type'), fn ($q) => $q->where('report_type', $request->string('report_type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('date_lost_or_found', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('date_lost_or_found', '<=', $request->date('date_to')))
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
                fn ($q) => $q->open()
            )
            ->latest();

        $itemReports = $query->paginate($request->integer('per_page', 15));

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
     * Item reports sorted nearest-first to the caller's GPS position.
     * GET /api/item-reports/nearby?lat=&lng=&radius_km=&category_id=&report_type=
     */
    public function nearby(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1'],
        ]);

        $lat = $request->float('lat');
        $lng = $request->float('lng');
        $radiusKm = $request->float('radius_km', 25);

        // 6371 = Earth's radius in km.
        $distanceExpr = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * '
            .'cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

        $query = ItemReport::query()
            ->select('*')
            ->selectRaw("{$distanceExpr} AS distance_km", [$lat, $lng, $lat])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->open()
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('report_type'), fn ($q) => $q->where('report_type', $request->string('report_type')))
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km')
            ->with(['user', 'category']);

        $itemReports = $query->paginate($request->integer('per_page', 15));

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
     * The authenticated user's own reports, any status. GET /api/my-item-reports
     */
    public function myItemReports(Request $request): JsonResponse
    {
        $itemReports = $request->user()
            ->itemReports()
            ->with(['category', 'claims.claimant'])
            ->latest()
            ->get();

        return response()->json(['data' => ItemReportResource::collection($itemReports)]);
    }

    public function store(StoreItemReportRequest $request): JsonResponse
    {
        $itemReport = $request->user()->itemReports()->create([
            ...$request->validated(),
            'status' => $request->validated('report_type'),
        ]);

        // create() doesn't reload column defaults set at the DB level, so
        // refresh to make sure the response reflects the true DB state.
        $itemReport->refresh()->load(['user', 'category']);

        return response()->json([
            'message' => 'Item report posted successfully.',
            'data' => new ItemReportResource($itemReport),
        ], 201);
    }

    public function show(ItemReport $itemReport): JsonResponse
    {
        $itemReport->load(['user', 'category', 'claims.claimant', 'claims.ratings']);

        return response()->json(['data' => new ItemReportResource($itemReport)]);
    }

    public function update(UpdateItemReportRequest $request, ItemReport $itemReport): JsonResponse
    {
        $itemReport->update($request->validated());

        return response()->json([
            'message' => 'Item report updated successfully.',
            'data' => new ItemReportResource($itemReport->fresh(['user', 'category'])),
        ]);
    }

    public function destroy(Request $request, ItemReport $itemReport): JsonResponse
    {
        if ($itemReport->user_id !== $request->user()->id) {
            abort(403, 'You can only delete your own item reports.');
        }

        $itemReport->delete();

        return response()->json(['message' => 'Item report deleted successfully.']);
    }
}
