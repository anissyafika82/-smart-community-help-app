<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A small, self-learning gazetteer for places OpenStreetMap doesn't know
 * about. See App\Models\CustomLocation for the full flow.
 */
class CustomLocationController extends Controller
{
    /**
     * GET /api/locations/search?q= — checked by the Flutter map picker
     * before it falls back to Nominatim.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'max:255']]);

        $location = CustomLocation::query()
            ->where('name', 'like', '%'.$request->string('q').'%')
            ->orderByDesc('search_count')
            ->first();

        if (! $location) {
            return response()->json(['data' => null]);
        }

        $location->increment('search_count');

        return response()->json(['data' => [
            'name' => $location->name,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        ]]);
    }

    /**
     * POST /api/locations — called every time a user's search successfully
     * resolves to a spot (either via Nominatim, or by manually correcting
     * one that came back empty), so the search box becomes a growing,
     * shared cache of name -> coordinate pairings for next time.
     */
    public function store(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $location = CustomLocation::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($fields['name'])])
            ->first();

        if ($location) {
            $location->increment('search_count');
            $location->update(['latitude' => $fields['latitude'], 'longitude' => $fields['longitude']]);
        } else {
            $location = CustomLocation::create($fields);
        }

        return response()->json(['message' => 'Saved.', 'data' => $location], 201);
    }
}
