<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\HelpOffer;
use App\Models\Message;
use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private readonly OneSignalService $notifications)
    {
    }

    /**
     * Message history between the authenticated user and $otherUser about
     * $helpOffer, oldest first. Marks the other user's messages as read.
     * GET /api/help-offers/{helpOffer}/chat/{user}/messages
     */
    public function index(Request $request, HelpOffer $helpOffer, User $user): JsonResponse
    {
        $me = $request->user();

        $messages = Message::where('help_offer_id', $helpOffer->id)
            ->where(function ($query) use ($me, $user) {
                $query->where(fn ($q) => $q->where('sender_id', $me->id)->where('recipient_id', $user->id))
                    ->orWhere(fn ($q) => $q->where('sender_id', $user->id)->where('recipient_id', $me->id));
            })
            ->with('sender')
            ->oldest()
            ->get();

        Message::where('help_offer_id', $helpOffer->id)
            ->where('sender_id', $user->id)
            ->where('recipient_id', $me->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => MessageResource::collection($messages)]);
    }

    /**
     * Send a message about $helpOffer to $otherUser, broadcast instantly.
     * POST /api/help-offers/{helpOffer}/chat/{user}/messages
     */
    public function store(StoreMessageRequest $request, HelpOffer $helpOffer, User $user): JsonResponse
    {
        $message = Message::create([
            'help_offer_id' => $helpOffer->id,
            'sender_id' => $request->user()->id,
            'recipient_id' => $user->id,
            'body' => $request->validated('body'),
        ]);
        $message->load('sender');

        broadcast(new MessageSent($message));

        $this->notifications->notifyUser(
            $user,
            "New message from {$message->sender->name}",
            $message->body,
            ['type' => 'new_message', 'help_offer_id' => $helpOffer->id, 'sender_id' => $message->sender_id],
        );

        return response()->json(['data' => new MessageResource($message)], 201);
    }

    /**
     * Every conversation the authenticated user is part of, one entry per
     * (help offer, other user) pair, with the latest message and unread count.
     * GET /api/my-chats
     */
    public function threads(Request $request): JsonResponse
    {
        $me = $request->user();

        $messages = Message::where('sender_id', $me->id)
            ->orWhere('recipient_id', $me->id)
            ->with(['helpOffer', 'sender', 'recipient'])
            ->latest()
            ->get();

        $threads = $messages
            ->groupBy(fn (Message $m) => $m->help_offer_id.'-'.($m->sender_id === $me->id ? $m->recipient_id : $m->sender_id))
            ->map(function ($group) use ($me) {
                /** @var Message $latest */
                $latest = $group->first();
                $otherUser = $latest->sender_id === $me->id ? $latest->recipient : $latest->sender;
                $unread = $group->where('recipient_id', $me->id)->whereNull('read_at')->count();

                return [
                    'help_offer_id' => $latest->help_offer_id,
                    'help_offer_title' => $latest->helpOffer?->title,
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
