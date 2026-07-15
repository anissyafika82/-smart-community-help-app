<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssistanceRequestResource extends JsonResource
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
            'status' => $this->status,
            'quantity' => $this->quantity,
            'priority' => $this->priority,
            'is_sos' => (bool) $this->is_sos,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'address' => $this->address,
            'helper_latitude' => $this->helper_latitude !== null ? (float) $this->helper_latitude : null,
            'helper_longitude' => $this->helper_longitude !== null ? (float) $this->helper_longitude : null,
            'helper_location_updated_at' => $this->helper_location_updated_at?->toIso8601String(),
            'distance_km' => $this->distance_km !== null ? round((float) $this->distance_km, 2) : null,
            'notes' => $this->notes,
            'proof_image_url' => $this->proof_image_url,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'help_offer' => new HelpOfferResource($this->whenLoaded('helpOffer')),
            'helper' => new UserResource($this->whenLoaded('helper')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'requester' => new UserResource($this->whenLoaded('requester')),
            'ratings' => RatingResource::collection($this->whenLoaded('ratings')),
        ];
    }
}
