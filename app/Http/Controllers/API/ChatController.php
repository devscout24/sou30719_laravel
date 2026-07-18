<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\Conversation;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserConnection;
use App\Services\ChatAttachmentUploadService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    use ApiResponse;

    public function __construct(protected ChatAttachmentUploadService $uploader)
    {
    }

    /**
     * Send a message (text and/or attachments) to a connected friend.
     * Creates the private conversation between the two users on first contact.
     */
    public function sendMessage(SendChatMessageRequest $request)
    {
        $sender     = Auth::guard('api')->user();
        $receiverId = (int) $request->validated()['user_id'];

        if ($sender->id === $receiverId) {
            return $this->error([], 'You cannot message yourself', 422);
        }

        $receiver = User::find($receiverId);

        if (!$receiver) {
            return $this->error([], 'User not found', 404);
        }

        if (UserBlock::isBlocked($sender->id, $receiverId)) {
            return $this->error([], 'You cannot message this user', 403);
        }

        $connection = $this->connectionBetween($sender->id, $receiverId);

        if (!$connection) {
            return $this->error([], 'You can only message users you are connected with', 403);
        }

        $conversation = $this->resolveConversation($connection);

        $message = DB::transaction(function () use ($conversation, $sender, $request) {
            $message = $conversation->messages()->create([
                'sender_id'    => $sender->id,
                'message_type' => $request->hasFile('attachments') ? 'attachment' : 'text',
                'message'      => $request->validated()['message'] ?? null,
            ]);

            foreach ($request->file('attachments', []) as $file) {
                $message->attachments()->create([
                    'file_path' => $this->uploader->store($file),
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }

            return $message;
        });

        $conversation->touch();

        return $this->success(
            new ChatMessageResource($message->load('attachments')),
            'Message sent successfully',
            201
        );
    }

    /**
     * Full, paginated message history for a conversation the authenticated user belongs to.
     * Also marks the other participant's messages as read.
     */
    public function conversation(Request $request, Conversation $conversation)
    {
        $userId = Auth::guard('api')->id();

        $isParticipant = $conversation->participants()->where('user_id', $userId)->exists();

        if (!$isParticipant) {
            return $this->error([], 'Conversation not found', 404);
        }

        $otherUser = $conversation->connection?->otherUser($userId);

        $perPage = min(max((int) $request->query('per_page', 30), 1), 100);

        $messages = $conversation->messages()
            ->with('attachments')
            ->latest()
            ->paginate($perPage);

        $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->unread()
            ->update(['is_read' => true]);

        return $this->success([
            'conversation_id' => $conversation->id,
            'user' => $otherUser ? [
                'id'       => $otherUser->id,
                'name'     => $otherUser->name,
                'username' => $otherUser->username,
                'avatar'   => asset($otherUser->avatar ?? 'user.png'),
            ] : null,
            'messages'   => ChatMessageResource::collection(collect($messages->items())->reverse()->values()),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'per_page'     => $messages->perPage(),
                'total'        => $messages->total(),
                'last_page'    => $messages->lastPage(),
            ],
        ], 'Conversation fetched successfully');
    }

    /**
     * All of the authenticated user's conversations, most recently active first.
     */
    public function recent(Request $request)
    {
        $userId  = Auth::guard('api')->id();
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        $conversations = Conversation::whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->whereHas('messages')
            ->withMax('messages', 'created_at')
            ->with('connection')
            ->orderByDesc('messages_max_created_at')
            ->paginate($perPage);

        $items = collect($conversations->items())
            ->map(function (Conversation $conversation) use ($userId) {
                $otherUser = $conversation->connection?->otherUser($userId);

                if (!$otherUser) {
                    return null;
                }

                $lastMessage = $conversation->latestMessage();
                $unreadCount = $conversation->messages()
                    ->where('sender_id', '!=', $userId)
                    ->unread()
                    ->count();

                return [
                    'conversation_id' => $conversation->id,
                    'user' => [
                        'id'       => $otherUser->id,
                        'name'     => $otherUser->name,
                        'username' => $otherUser->username,
                        'avatar'   => asset($otherUser->avatar ?? 'user.png'),
                    ],
                    'last_message' => $lastMessage ? [
                        'id'         => $lastMessage->id,
                        'text'       => $lastMessage->hasAttachment() && blank($lastMessage->message)
                            ? '📎 Attachment'
                            : $lastMessage->message,
                        'is_mine'    => $lastMessage->sender_id === $userId,
                        'created_at' => $lastMessage->created_at,
                    ] : null,
                    'unread_count' => $unreadCount,
                ];
            })
            ->filter()
            ->values();

        return $this->success([
            'chats' => $items,
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'per_page'     => $conversations->perPage(),
                'total'        => $conversations->total(),
                'last_page'    => $conversations->lastPage(),
            ],
        ], 'Recent chats fetched successfully');
    }

    // ─── Helpers ─────────────────────────────────────

    private function connectionBetween(int $userId, int $otherUserId): ?UserConnection
    {
        [$one, $two] = $userId < $otherUserId ? [$userId, $otherUserId] : [$otherUserId, $userId];

        return UserConnection::where('user_one_id', $one)->where('user_two_id', $two)->first();
    }

    private function resolveConversation(UserConnection $connection): Conversation
    {
        return DB::transaction(function () use ($connection) {
            $conversation = $connection->conversation()->first();

            if ($conversation) {
                return $conversation;
            }

            $conversation = Conversation::create([
                'connection_id' => $connection->id,
                'type'          => 'private',
            ]);

            $conversation->participants()->createMany([
                ['user_id' => $connection->user_one_id, 'joined_at' => now()],
                ['user_id' => $connection->user_two_id, 'joined_at' => now()],
            ]);

            return $conversation;
        });
    }
}
