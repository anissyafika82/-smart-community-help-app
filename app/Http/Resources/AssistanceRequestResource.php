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
            'notes' => $this->notes,
            'proof_image_url' => $this->proof_image_url,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'help_offer' => new HelpOfferResource($this->whenLoaded('helpOffer')),
            'requester' => new UserResource($this->whenLoaded('requester')),
            'ratings' => RatingResource::collection($this->whenLoaded('ratings')),
        ];
    }
}
