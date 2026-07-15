<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemClaimResource extends JsonResource
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
            'claim_message' => $this->claim_message,
            'proof_description' => $this->proof_description,
            'proof_image_url' => $this->proof_image_url,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'returned_at' => $this->returned_at?->toIso8601String(),
            'item_report' => new ItemReportResource($this->whenLoaded('itemReport')),
            'claimant' => new UserResource($this->whenLoaded('claimant')),
            'ratings' => RatingResource::collection($this->whenLoaded('ratings')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
