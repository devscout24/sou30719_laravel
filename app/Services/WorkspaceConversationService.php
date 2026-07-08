<?php

namespace App\Services;

use App\Exceptions\AIServiceException;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Post;
use App\Models\Workspace;
use App\Services\AI\PostCuratorService;
use App\Services\AI\ReplyIntentClassifierService;
use App\Services\AI\WorkspaceIntentClassifierService;
use Illuminate\Support\Facades\DB;

class WorkspaceConversationService
{
    protected const PILL_APPROVE = 'Approve posting to the feed';
    protected const PILL_EDIT = 'Edit post';
    protected const PILL_DELETE = 'Delete post';
    protected const PILL_CONFIRM_YES = "Yes, that's right";
    protected const PILL_CONFIRM_NO = 'No, something else';

    protected const MSG_SELECT_PROMPT = 'Select one of the optional prompts below';
    protected const MSG_UNDER_DEV = 'This feature is currently under development and will be available soon.';
    protected const MSG_SOCIAL_GUIDANCE = 'Share your thoughts and experiences inside the chat interface and attach image(s) to proceed.';
    protected const MSG_NEED_DESCRIPTION = 'Please provide a description for your post.';
    protected const MSG_NEED_IMAGES = 'Please upload at least one image.';
    protected const MSG_NEED_BOTH = 'Please provide a description and upload at least one image.';
    protected const MSG_PUBLISHED = 'Your post has been published successfully.';
    protected const MSG_DRAFT_DELETED = 'Draft deleted successfully.';
    protected const MSG_ASK_EDIT_INSTRUCTION = 'What would you like to change about your post?';
    protected const MSG_CONVERSATION_DONE = 'This conversation has already been completed. Start a new conversation to create another post.';
    protected const MSG_CHOOSE_OPTION = 'Please choose one of the options below.';
    protected const MSG_CLARIFY_INTENT = "I couldn't quite tell what you're looking to do. Could you be more specific, or choose one of the options below?";

    public function __construct(
        protected PostCuratorService $curator,
        protected WorkspaceIntentClassifierService $classifier,
        protected ReplyIntentClassifierService $replyClassifier,
    ) {
    }

    // ─── Public API ──────────────────────────────────────────────────────────────

    /**
     * Start a new conversation, optionally pre-selecting a workspace (card click).
     *
     * Returns only the conversation ID and slug — no messages, pills, or previews.
     * The frontend should call the Details endpoint to load the full state.
     *
     * @return array{conversation_id: int, slug: string}
     */
    public function startConversation(int $userId, ?int $workspaceId = null): array
    {
        $conversation = AiConversation::create([
            'user_id' => $userId,
            'status'  => 'idle',
        ]);

        if ($workspaceId) {
            $workspace = Workspace::active()->find($workspaceId);

            if ($workspace) {
                $this->assignWorkspace($conversation, $workspace);

                return [
                    'conversation_id' => $conversation->id,
                    'slug'            => $conversation->slug,
                ];
            }
        }

        // No workspace pre-selected — store the initial prompt + pills
        $this->storeReply($conversation, self::MSG_SELECT_PROMPT);
        $this->storePills($conversation, $this->activePrompts());

        return [
            'conversation_id' => $conversation->id,
            'slug'            => $conversation->slug,
        ];
    }

    /**
     * Advance the conversation's state machine with an incoming chat message.
     *
     * Returns only a success flag. The frontend should call the Details endpoint
     * to get the updated conversation state.
     *
     * @param  string[]  $imagePaths
     * @return array{success: true}
     */
    public function handleMessage(AiConversation $conversation, ?string $text, array $imagePaths): array
    {
        $this->recordUserMessage($conversation, $text, $imagePaths);

        match ($conversation->status) {
            'idle'                      => $this->handleIdle($conversation, $text),
            'confirming_workspace'      => $this->handleConfirmingWorkspace($conversation, $text),
            'collecting'                => $this->handleCollecting($conversation, $text, $imagePaths),
            'preview'                   => $this->handlePreview($conversation, $text),
            'awaiting_edit_instruction' => $this->handleEditInstruction($conversation, $text),
            default                     => $this->storeReply($conversation, self::MSG_CONVERSATION_DONE),
        };

        return ['success' => true];
    }

    // ─── State Handlers (persistence only, no return payloads) ────────────────

    protected function handleIdle(AiConversation $conversation, ?string $text): void
    {
        $workspace = $this->matchWorkspaceExact($text);

        if ($workspace) {
            $this->assignWorkspace($conversation, $workspace);
            return;
        }

        if (blank($text)) {
            $this->storeReply($conversation, self::MSG_SELECT_PROMPT);
            $this->storePills($conversation, $this->activePrompts());
            return;
        }

        $result = $this->classifier->interpret($text, Workspace::active()->get(), $this->recentHistory($conversation));

        if (!$result['workspace']) {
            $this->storeReply($conversation, $result['reply']);
            $this->storePills($conversation, $this->activePrompts());
            return;
        }

        $conversation->update(['workspace_id' => $result['workspace']->id, 'status' => 'confirming_workspace']);

        $this->storeReply(
            $conversation,
            sprintf('It sounds like you want to: "%s" — is that right?', $result['workspace']->prompt)
        );
        $this->storePills($conversation, $this->confirmationPills());
    }

    protected function handleConfirmingWorkspace(AiConversation $conversation, ?string $text): void
    {
        $decision = $this->replyClassifier->classifyConfirmation((string) $text);

        if ($decision === 'yes') {
            $workspace = Workspace::find($conversation->workspace_id);

            if (!$workspace) {
                $conversation->update(['workspace_id' => null, 'status' => 'idle']);
                $this->storeReply($conversation, self::MSG_CLARIFY_INTENT);
                $this->storePills($conversation, $this->activePrompts());
                return;
            }

            $this->assignWorkspace($conversation, $workspace);
            return;
        }

        $conversation->update(['workspace_id' => null, 'status' => 'idle']);

        if ($decision === 'no') {
            $this->storeReply($conversation, self::MSG_CLARIFY_INTENT);
            $this->storePills($conversation, $this->activePrompts());
            return;
        }

        // Unclear reply — treat it as a fresh attempt to describe their intent.
        $this->handleIdle($conversation, $text);
    }

    protected function assignWorkspace(AiConversation $conversation, Workspace $workspace): void
    {
        if (!$workspace->is_supported) {
            $conversation->update(['workspace_id' => null, 'status' => 'idle']);
            $this->storeReply($conversation, self::MSG_UNDER_DEV);
            $this->storePills($conversation, $this->activePrompts());
            return;
        }

        $conversation->update(['workspace_id' => $workspace->id, 'status' => 'collecting']);
        $this->storeReply($conversation, $this->guidanceFor($workspace));
    }

    protected function handleCollecting(AiConversation $conversation, ?string $text, array $imagePaths): void
    {
        $description = $conversation->description;
        $images      = $conversation->images ?? [];

        if (filled($text)) {
            $description = trim($text);
        }

        if (!empty($imagePaths)) {
            $images = array_merge($images, $imagePaths);
        }

        // Preserve the user's original wording as image_description before AI polishes it
        $imageDescription = filled($text) ? trim($text) : $conversation->image_description;

        $conversation->update([
            'description'       => $description,
            'image_description' => $imageDescription,
            'images'            => $images,
        ]);

        $hasDescription = $conversation->hasDescription();
        $hasImages      = $conversation->hasImages();

        if (!$hasDescription && !$hasImages) {
            $this->storeReply($conversation, self::MSG_NEED_BOTH);
            return;
        }

        if (!$hasDescription) {
            $this->storeReply($conversation, self::MSG_NEED_DESCRIPTION);
            return;
        }

        if (!$hasImages) {
            $this->storeReply($conversation, self::MSG_NEED_IMAGES);
            return;
        }

        try {
            $result = $this->curator->curate($conversation->description, $conversation->images);
        } catch (AIServiceException $e) {
            $this->storeReply($conversation, $e->getMessage());
            return;
        }

        $conversation->update([
            'topic'             => $result['topic'],
            'description'       => $result['description'],
            'short_description' => $result['short_description'],
            'tags'              => $result['tags'],
            'status'            => 'preview',
        ]);

        $this->storePostPreview($conversation);
        $this->storePills($conversation, $this->previewPills());
    }

    protected function handlePreview(AiConversation $conversation, ?string $text): void
    {
        $action = $this->replyClassifier->classifyPreviewAction((string) $text);

        match ($action) {
            'approve' => $this->approve($conversation),
            'edit'    => $this->beginEditInstruction($conversation),
            'delete'  => $this->deleteDraft($conversation),
            default   => (function () use ($conversation) {
                $this->storeReply($conversation, self::MSG_CHOOSE_OPTION);
                $this->storePills($conversation, $this->previewPills());
            })(),
        };
    }

    protected function beginEditInstruction(AiConversation $conversation): void
    {
        $conversation->update(['status' => 'awaiting_edit_instruction']);
        $this->storeReply($conversation, self::MSG_ASK_EDIT_INSTRUCTION);
    }

    protected function handleEditInstruction(AiConversation $conversation, ?string $text): void
    {
        if (blank($text)) {
            $this->storeReply($conversation, self::MSG_ASK_EDIT_INSTRUCTION);
            return;
        }

        try {
            $result = $this->curator->refine($conversation->topic, $conversation->description, $text);
        } catch (AIServiceException $e) {
            $this->storeReply($conversation, $e->getMessage());
            return;
        }

        $conversation->update([
            'topic'             => $result['topic'],
            'description'       => $result['description'],
            'short_description' => $result['short_description'],
            'tags'              => $result['tags'],
            'status'            => 'preview',
        ]);

        $this->storePostPreview($conversation);
        $this->storePills($conversation, $this->previewPills());
    }

    protected function approve(AiConversation $conversation): void
    {
        $post = DB::transaction(function () use ($conversation) {
            $post = Post::create([
                'user_id'           => $conversation->user_id,
                'workspace_id'      => $conversation->workspace_id,
                'topic'             => $conversation->topic,
                'type'              => 'regular',
                // User created the post using AI assistance — it belongs to the user
                'created_by'        => 'user',
                'content'           => $conversation->description,
                'short_description' => $conversation->short_description,
                'image_description' => $conversation->image_description,
                'tags'              => $conversation->tags ?? [],
                'visibility'        => 'public',
                'status'            => 'published',
                'published_at'      => now(),
            ]);

            foreach (($conversation->images ?? []) as $index => $path) {
                $post->images()->create(['image_path' => $path, 'sort_order' => $index]);
            }

            $conversation->update(['status' => 'published', 'post_id' => $post->id]);

            return $post;
        });

        $this->storeReply($conversation, self::MSG_PUBLISHED);
    }

    protected function deleteDraft(AiConversation $conversation): void
    {
        $conversation->delete();

        // No messages to store — the conversation is deleted.
        // The frontend will receive a success response and can redirect.
    }

    // ─── Message Persistence ─────────────────────────────────────────────────

    /**
     * Store a plain text AI reply message.
     */
    protected function storeReply(AiConversation $conversation, string $message): AiMessage
    {
        return AiMessage::create([
            'conversation_id' => $conversation->id,
            'sender'          => 'ai',
            'type'            => 'message',
            'message'         => $message,
        ]);
    }

    /**
     * Store pills as a separate timeline entry so the frontend can render them distinctly.
     */
    protected function storePills(AiConversation $conversation, array $pills): AiMessage
    {
        return AiMessage::create([
            'conversation_id' => $conversation->id,
            'sender'          => 'ai',
            'type'            => 'pills',
            'message'         => json_encode($pills),
        ]);
    }

    /**
     * Store an AI-generated post preview card.
     */
    protected function storePostPreview(AiConversation $conversation): AiMessage
    {
        $payload = [
            'topic'             => $conversation->topic,
            'description'       => $conversation->description,
            'short_description' => $conversation->short_description,
            'tags'              => $conversation->tags ?? [],
            'images'            => array_map(
                fn (string $path) => ['path' => 'storage/' . $path],
                $conversation->images ?? []
            ),
        ];

        return AiMessage::create([
            'conversation_id' => $conversation->id,
            'sender'          => 'ai',
            'type'            => 'post',
            'message'         => json_encode($payload),
        ]);
    }

    /**
     * Record the user's incoming message (text and/or images).
     */
    protected function recordUserMessage(AiConversation $conversation, ?string $text, array $imagePaths): void
    {
        if (blank($text) && empty($imagePaths)) {
            return;
        }

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'sender'          => 'user',
            'type'            => 'message',
            'message'         => (string) ($text ?? ''),
            'attachments'     => !empty($imagePaths) ? $imagePaths : null,
        ]);
    }

    // ─── Conversation History (for AI context) ───────────────────────────────

    /**
     * Prior conversation turns as OpenAI-style role/content pairs, oldest first,
     * excluding the current turn (already recorded and passed separately).
     *
     * @return array<int, array{role: string, content: string}>
     */
    protected function recentHistory(AiConversation $conversation, int $limit = 10): array
    {
        $messages = $conversation->messages()
            ->orderByDesc('created_at')
            ->limit($limit + 1)
            ->get()
            ->reverse()
            ->values();

        if ($messages->isNotEmpty()) {
            $messages = $messages->slice(0, -1);
        }

        return $messages
            ->filter(fn (AiMessage $message) => $message->type === 'message')
            ->map(fn (AiMessage $message) => [
                'role'    => $message->sender === 'user' ? 'user' : 'assistant',
                'content' => $this->extractChatText($message),
            ])
            ->filter(fn (array $entry) => $entry['content'] !== '')
            ->values()
            ->all();
    }

    /**
     * Extract plain chat text from a message for AI context.
     */
    protected function extractChatText(AiMessage $message): string
    {
        // For user messages, the text is stored directly in the message column now.
        // For AI messages of type 'message', the raw string is the text.
        // Skip non-message types (pills, post previews) — they aren't useful as chat context.
        if ($message->type !== 'message') {
            return '';
        }

        return (string) $message->message;
    }

    // ─── Pill Helpers ────────────────────────────────────────────────────────

    protected function confirmationPills(): array
    {
        return [self::PILL_CONFIRM_YES, self::PILL_CONFIRM_NO];
    }

    protected function previewPills(): array
    {
        return [self::PILL_APPROVE, self::PILL_EDIT, self::PILL_DELETE];
    }

    protected function guidanceFor(Workspace $workspace): string
    {
        return match ($workspace->slug) {
            'social_post' => self::MSG_SOCIAL_GUIDANCE,
            default       => self::MSG_UNDER_DEV,
        };
    }

    protected function matchWorkspaceExact(?string $text): ?Workspace
    {
        if (blank($text)) {
            return null;
        }

        $normalized = mb_strtolower(trim($text));

        return Workspace::active()->get()->first(
            fn (Workspace $workspace) => mb_strtolower(trim($workspace->prompt)) === $normalized
        );
    }

    protected function activePrompts(): array
    {
        return Workspace::active()->orderBy('sort_order')->pluck('prompt')->all();
    }
}
