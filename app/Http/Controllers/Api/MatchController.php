<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemReportResource;
use App\Models\ItemReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MatchController extends Controller
{
    /**
     * Candidate matches for one item report: same category, the opposite
     * report type (a "lost" report matches against "found" reports and
     * vice versa), still open. Ranked by keyword overlap in the item name
     * first, then by distance if both reports have coordinates.
     * GET /api/item-reports/{itemReport}/matches
     */
    public function forItemReport(ItemReport $itemReport): JsonResponse
    {
        $keywords = collect(preg_split('/\s+/', trim($itemReport->item_name)))
            ->filter(fn ($word) => Str::length($word) >= 3)
            ->map(fn ($word) => '%'.$word.'%');

        $query = ItemReport::query()
            ->where('id', '!=', $itemReport->id)
            ->where('category_id', $itemReport->category_id)
            ->where('report_type', $itemReport->oppositeType())
            ->open()
            ->with(['user', 'category']);

        if ($keywords->isNotEmpty()) {
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $q->orWhere('item_name', 'like', $keyword)
                        ->orWhere('description', 'like', $keyword);
                }
            });
        }

        if ($itemReport->latitude !== null && $itemReport->longitude !== null) {
            $distanceExpr = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * '
                .'cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

            $query->selectRaw('item_reports.*, '.$distanceExpr.' AS distance_km', [
                (float) $itemReport->latitude,
                (float) $itemReport->longitude,
                (float) $itemReport->latitude,
            ])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('distance_km');
        } else {
            $query->latest();
        }

        $matches = $query->limit(20)->get();

        // Flag both sides as a potential match once the system has
        // actually surfaced candidates, so the status is visible without
        // requiring either party to take an action first.
        if ($matches->isNotEmpty()) {
            $itemReport->update(['status' => ItemReport::STATUS_POTENTIAL_MATCH]);
            ItemReport::whereIn('id', $matches->pluck('id'))
                ->whereIn('status', [ItemReport::STATUS_LOST, ItemReport::STATUS_FOUND])
                ->update(['status' => ItemReport::STATUS_POTENTIAL_MATCH]);
        }

        return response()->json(['data' => ItemReportResource::collection($matches)]);
    }
}
