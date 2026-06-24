<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\SendMessageRequest;
use App\Http\Requests\Conversation\StartConversationRequest;
use App\Models\AiConversation;
use App\Services\PostImageUploadService;
use App\Services\WorkspaceConversationService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WorkspaceConversationService $conversations,
        protected PostImageUploadService $uploader,
    ) {
    }

    /**
     * Start a new AI workspace conversation, optionally pre-selecting a workspace.
     */
    public function store(StartConversationRequest $request)
    {
        $userId = Auth::guard('api')->id();

        $result = $this->conversations->startConversation($userId, $request->validated()['workspace_id'] ?? null);

        return $this->success($result, 'Conversation started');
    }

    /**
     * Fetch a conversation with its full message history.
     */
    public function show(int $id)
    {
        $userId = Auth::guard('api')->id();

        $conversation = AiConversation::where('id', $id)->where('user_id', $userId)->with('messages')->first();

        if (!$conversation) {
            return $this->error([], 'Conversation not found', 404);
        }

        return $this->success([
            'id' => $conversation->id,
            'workspace_id' => $conversation->workspace_id,
            'status' => $conversation->status,
            'messages' => $conversation->messages->map(fn ($message) => [
                'sender' => $message->sender,
                'message' => $this->decodeMessage($message->message),
                'created_at' => $message->created_at?->toISOString(),
            ]),
        ], 'Conversation fetched successfully');
    }

    /**
     * Send a chat message (text and/or images) and advance the conversation.
     */
    public function message(SendMessageRequest $request, int $id)
    {
        $userId = Auth::guard('api')->id();

        $conversation = AiConversation::where('id', $id)->where('user_id', $userId)->first();

        if (!$conversation) {
            return $this->error([], 'Conversation not found', 404);
        }

        $imagePaths = $request->hasFile('images')
            ? $this->uploader->storeMany($request->file('images'))
            : [];

        $result = $this->conversations->handleMessage($conversation, $request->validated()['message'] ?? null, $imagePaths);

        return $this->success($result, 'Message processed');
    }

    protected function decodeMessage(string $message): mixed
    {
        $decoded = json_decode($message, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $message;
    }
}
