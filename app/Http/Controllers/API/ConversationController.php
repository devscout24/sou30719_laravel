<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\SendMessageRequest;
use App\Http\Requests\Conversation\StartConversationRequest;
use App\Http\Resources\ConversationDetailResource;
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
     *
     * Returns only the conversation ID and slug.
     * The frontend should immediately call the Details endpoint to load the full state.
     */
    public function store(StartConversationRequest $request)
    {
        $userId = Auth::guard('api')->id();

        $result = $this->conversations->startConversation($userId, $request->validated()['workspace_id'] ?? null);

        return $this->success([
            'conversation_id' => $result['conversation_id'],
            'slug'            => $result['slug'],
        ], 'Conversation created successfully');
    }

    /**
     * Fetch a conversation with its full message history.
     *
     * This is the **single source of truth** for all conversation state.
     * Every piece of conversation-related information is returned here.
     */
    public function show(string $slug)
    {
        $userId = Auth::guard('api')->id();

        $conversation = AiConversation::where('slug', $slug)
            ->where('user_id', $userId)
            ->with(['messages', 'workspace', 'post'])
            ->first();

        if (!$conversation) {
            return $this->error([], 'Conversation not found', 404);
        }

        return $this->success(
            new ConversationDetailResource($conversation),
            'Conversation details'
        );
    }

    /**
     * Send a chat message (text and/or images) and advance the conversation.
     *
     * Returns only a success status. The frontend should immediately call
     * the Details endpoint to get the updated conversation state.
     */
    public function message(SendMessageRequest $request, string $slug)
    {
        $userId = Auth::guard('api')->id();

        $conversation = AiConversation::where('slug', $slug)->where('user_id', $userId)->first();

        if (!$conversation) {
            return $this->error([], 'Conversation not found', 404);
        }

        $imagePaths = $request->hasFile('images')
            ? $this->uploader->storeMany($request->file('images'))
            : [];

        $validated = $request->validated();

        $extra = collect($validated)
            ->only(['ad_type', 'category', 'product_url', 'discount_percentage', 'show_sale_badge'])
            ->filter(fn ($value) => $value !== null)
            ->all();

        $this->conversations->handleMessage($conversation, $validated['message'] ?? null, $imagePaths, $extra);

        return $this->success([], 'Message sent successfully');
    }
}
