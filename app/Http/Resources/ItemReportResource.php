<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_type' => $this->report_type,
            'item_name' => $this->item_name,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'date_lost_or_found' => $this->date_lost_or_found?->toDateString(),
            'location_name' => $this->location_name,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'status' => $this->status,
            'identifying_details' => $this->identifying_details,
            'distance_km' => $this->distance_km !== null ? round((float) $this->distance_km, 2) : null,
            'user' => new UserResource($this->whenLoaded('user')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            // Multiple claimants can each submit a claim against the same
            // item report until one is verified.
            'claims' => ItemClaimResource::collection($this->whenLoaded('claims')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
