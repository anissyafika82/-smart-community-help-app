<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HelpOfferResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'available_until' => $this->available_until?->toIso8601String(),
            'image_url' => $this->image_url,
            'location_address' => $this->location_address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'status' => $this->status,
            'distance_km' => $this->distance_km !== null ? round((float) $this->distance_km, 2) : null,
            'helper' => new UserResource($this->whenLoaded('helper')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            // Multiple requesters can each hold a request against the same
            // help offer until its stock (quantity) runs out.
            'assistance_requests' => AssistanceRequestResource::collection($this->whenLoaded('assistanceRequests')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
