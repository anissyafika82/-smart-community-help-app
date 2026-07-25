<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\ItemReport;
use App\Models\Message;
use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __construct(private readonly OneSignalService $notifications)
    {
    }

    /**
     * Message history between the authenticated user and $otherUser about
     * $itemReport, oldest first. Marks the other user's messages as read.
     * GET /api/item-reports/{itemReport}/chat/{user}/messages
     */
    public function index(Request $request, ItemReport $itemReport, User $user): JsonResponse
    {
        $me = $request->user();

        $messages = Message::where('item_report_id', $itemReport->id)
            ->where(function ($query) use ($me, $user) {
                $query->where(fn ($q) => $q->where('sender_id', $me->id)->where('recipient_id', $user->id))
                    ->orWhere(fn ($q) => $q->where('sender_id', $user->id)->where('recipient_id', $me->id));
            })
            ->with('sender')
            ->oldest()
            ->get();

        Message::where('item_report_id', $itemReport->id)
            ->where('sender_id', $user->id)
            ->where('recipient_id', $me->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => MessageResource::collection($messages)]);
    }

    /**
     * Send a message about $itemReport to $otherUser, broadcast instantly.
     * POST /api/item-reports/{itemReport}/chat/{user}/messages
     */
    public function store(StoreMessageRequest $request, ItemReport $itemReport, User $user): JsonResponse
    {
        $message = Message::create([
            'item_report_id' => $itemReport->id,
            'sender_id' => $request->user()->id,
            'recipient_id' => $user->id,
            'body' => $request->validated('body'),
        ]);
        $message->load('sender');

        // The message is already saved at this point — a live-push failure
        // (e.g. the Reverb server being unreachable) shouldn't fail the
        // whole request. The recipient still gets it via the REST history
        // endpoint, just without the instant real-time delivery.
        try {
            broadcast(new MessageSent($message));
        } catch (BroadcastException $e) {
            Log::warning('Chat message broadcast failed: '.$e->getMessage());
        }

        $this->notifications->notifyUser(
            $user,
            "New message from {$message->sender->name}",
            $message->body,
            ['type' => 'new_message', 'item_report_id' => $itemReport->id, 'sender_id' => $message->sender_id],
        );

        return response()->json(['data' => new MessageResource($message)], 201);
    }

    /**
     * Every conversation the authenticated user is part of, one entry per
     * (item report, other user) pair, with the latest message and unread count.
     * GET /api/my-chats
     */
    public function threads(Request $request): JsonResponse
    {
        $me = $request->user();

        $messages = Message::where('sender_id', $me->id)
            ->orWhere('recipient_id', $me->id)
            ->with(['itemReport', 'sender', 'recipient'])
            ->latest()
            ->get();

        $threads = $messages
            ->groupBy(fn (Message $m) => $m->item_report_id.'-'.($m->sender_id === $me->id ? $m->recipient_id : $m->sender_id))
            ->map(function ($group) use ($me) {
                /** @var Message $latest */
                $latest = $group->first();
                $otherUser = $latest->sender_id === $me->id ? $latest->recipient : $latest->sender;
                $unread = $group->where('recipient_id', $me->id)->whereNull('read_at')->count();

                return [
                    'item_report_id' => $latest->item_report_id,
                    'item_report_name' => $latest->itemReport?->item_name,
                    'other_user' => $otherUser ? [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'avatar_url' => $otherUser->avatar_url,
                    ] : null,
                    'last_message' => $latest->body,
                    'last_message_at' => $latest->created_at?->toIso8601String(),
                    'unread_count' => $unread,
                ];
            })
            ->values();

        return response()->json(['data' => $threads]);
    }
}
