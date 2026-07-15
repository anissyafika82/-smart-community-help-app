<?php

namespace App\Events;

use App\Models\AssistanceRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts a volunteer's live position while they're en route to an
 * accepted request, so the requester's app can update the map in
 * real time. Uses ShouldBroadcastNow for the same reason as MessageSent —
 * no queue worker needed for a local/demo deployment.
 */
class HelperLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AssistanceRequest $assistanceRequest)
    {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tracking.{$this->assistanceRequest->id}")];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'assistance_request_id' => $this->assistanceRequest->id,
            'latitude' => (float) $this->assistanceRequest->helper_latitude,
            'longitude' => (float) $this->assistanceRequest->helper_longitude,
            'updated_at' => $this->assistanceRequest->helper_location_updated_at?->toIso8601String(),
        ];
    }
}
