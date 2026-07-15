<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
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
            'reason' => $this->reason,
            'description' => $this->description,
            'status' => $this->status,
            'admin_notes' => $this->admin_notes,
            'reporter' => new UserResource($this->whenLoaded('reporter')),
            'reported_user' => new UserResource($this->whenLoaded('reportedUser')),
            'assistance_request' => new AssistanceRequestResource($this->whenLoaded('assistanceRequest')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
