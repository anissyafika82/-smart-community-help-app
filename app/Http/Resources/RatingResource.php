<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingResource extends JsonResource
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
            'stars' => $this->stars,
            'comment' => $this->comment,
            'rated_by' => new UserResource($this->whenLoaded('ratedBy')),
            'rated_user' => new UserResource($this->whenLoaded('ratedUser')),
            'assistance_request' => new AssistanceRequestResource($this->whenLoaded('assistanceRequest')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
