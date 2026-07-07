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

    /**
     * Start a new conversation, optionally pre-selecting a workspace (card click).
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
                return $this->assignWorkspace($conversation, $workspace);
            }
        }

        return $this->reply($conversation, self::MSG_SELECT_PROMPT, pills: $this->activePrompts());
    }

    /**
     * Advance the conversation's state machine with an incoming chat message.
     *
     * @param  string[]  $imagePaths
     */
    public function handleMessage(AiConversation $conversation, ?string $text, array $imagePaths): array
    {
        $this->recordUserMessage($conversation, $text, $imagePaths);

        return match ($conversation->status) {
            'idle'                      => $this->handleIdle($conversation, $text),
            'confirming_workspace'      => $this->handleConfirmingWorkspace($conversation, $text),
            'collecting'                => $this->handleCollecting($conversation, $text, $imagePaths),
            'preview'                   => $this->handlePreview($conversation, $text),
            'awaiting_edit_instruction' => $this->handleEditInstruction($conversation, $text),
            default                     => $this->reply($conversation, self::MSG_CONVERSATION_DONE),
        };
    }

    protected function handleIdle(AiConversation $conversation, ?string $text): array
    {
        $workspace = $this->matchWorkspaceExact($text);

        if ($workspace) {
            return $this->assignWorkspace($conversation, $workspace);
        }

        if (blank($text)) {
            return $this->reply($conversation, self::MSG_SELECT_PROMPT, pills: $this->activePrompts());
        }

        $result = $this->classifier->interpret($text, Workspace::active()->get(), $this->recentHistory($conversation));

        if (!$result['workspace']) {
            return $this->reply($conversation, $result['reply'], pills: $this->activePrompts());
        }

        $conversation->update(['workspace_id' => $result['workspace']->id, 'status' => 'confirming_workspace']);

        return $this->reply(
            $conversation,
            sprintf('It sounds like you want to: "%s" — is that right?', $result['workspace']->prompt),
            pills: $this->confirmationPills()
        );
    }

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
            ->map(fn (AiMessage $message) => [
                'role'    => $message->sender === 'user' ? 'user' : 'assistant',
                'content' => $this->extractChatText($message->message),
            ])
            ->filter(fn (array $entry) => $entry['content'] !== '')
            ->values()
            ->all();
    }

    protected function extractChatText(string $raw): string
    {
        $decoded = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // User turns with images are stored as {text, images}; AI preview payloads
            // (topic/description/tags) aren't useful as chat context, so skip them.
            return array_key_exists('text', $decoded) ? (string) ($decoded['text'] ?? '') : '';
        }

        return $raw;
    }

    protected function handleConfirmingWorkspace(AiConversation $conversation, ?string $text): array
    {
        $decision = $this->replyClassifier->classifyConfirmation((string) $text);

        if ($decision === 'yes') {
            $workspace = Workspace::find($conversation->workspace_id);

            if (!$workspace) {
                $conversation->update(['workspace_id' => null, 'status' => 'idle']);

                return $this->reply($conversation, self::MSG_CLARIFY_INTENT, pills: $this->activePrompts());
            }

            return $this->assignWorkspace($conversation, $workspace);
        }

        $conversation->update(['workspace_id' => null, 'status' => 'idle']);

        if ($decision === 'no') {
            return $this->reply($conversation, self::MSG_CLARIFY_INTENT, pills: $this->activePrompts());
        }

        // Unclear reply — treat it as a fresh attempt to describe their intent.
        return $this->handleIdle($conversation, $text);
    }

    protected function assignWorkspace(AiConversation $conversation, Workspace $workspace): array
    {
        if (!$workspace->is_supported) {
            $conversation->update(['workspace_id' => null, 'status' => 'idle']);

            return $this->reply($conversation, self::MSG_UNDER_DEV, pills: $this->activePrompts());
        }

        $conversation->update(['workspace_id' => $workspace->id, 'status' => 'collecting']);

        return $this->reply($conversation, $this->guidanceFor($workspace));
    }

    protected function confirmationPills(): array
    {
        return [self::PILL_CONFIRM_YES, self::PILL_CONFIRM_NO];
    }

    protected function handleCollecting(AiConversation $conversation, ?string $text, array $imagePaths): array
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
            return $this->reply($conversation, self::MSG_NEED_BOTH);
        }

        if (!$hasDescription) {
            return $this->reply($conversation, self::MSG_NEED_DESCRIPTION);
        }

        if (!$hasImages) {
            return $this->reply($conversation, self::MSG_NEED_IMAGES);
        }

        try {
            $result = $this->curator->curate($conversation->description, $conversation->images);
        } catch (AIServiceException $e) {
            return $this->reply($conversation, $e->getMessage());
        }

        $conversation->update([
            'topic'             => $result['topic'],
            'description'       => $result['description'],
            'short_description' => $result['short_description'],
            'tags'              => $result['tags'],
            'status'            => 'preview',
        ]);

        return $this->previewReply($conversation);
    }

    protected function handlePreview(AiConversation $conversation, ?string $text): array
    {
        $action = $this->replyClassifier->classifyPreviewAction((string) $text);

        return match ($action) {
            'approve' => $this->approve($conversation),
            'edit'    => $this->beginEditInstruction($conversation),
            'delete'  => $this->deleteDraft($conversation),
            default   => $this->reply($conversation, self::MSG_CHOOSE_OPTION, pills: $this->previewPills()),
        };
    }

    protected function beginEditInstruction(AiConversation $conversation): array
    {
        $conversation->update(['status' => 'awaiting_edit_instruction']);

        return $this->reply($conversation, self::MSG_ASK_EDIT_INSTRUCTION);
    }

    protected function handleEditInstruction(AiConversation $conversation, ?string $text): array
    {
        if (blank($text)) {
            return $this->reply($conversation, self::MSG_ASK_EDIT_INSTRUCTION);
        }

        try {
            $result = $this->curator->refine($conversation->topic, $conversation->description, $text);
        } catch (AIServiceException $e) {
            return $this->reply($conversation, $e->getMessage());
        }

        $conversation->update([
            'topic'             => $result['topic'],
            'description'       => $result['description'],
            'short_description' => $result['short_description'],
            'tags'              => $result['tags'],
            'status'            => 'preview',
        ]);

        return $this->previewReply($conversation);
    }

    protected function approve(AiConversation $conversation): array
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

        return $this->reply($conversation, self::MSG_PUBLISHED, extra: ['post_id' => $post->id]);
    }

    protected function deleteDraft(AiConversation $conversation): array
    {
        $conversationId = $conversation->id;

        $conversation->delete();

        return [
            'conversation_id' => $conversationId,
            'message'         => self::MSG_DRAFT_DELETED,
            'preview'         => null,
            'pills'           => null,
            'status'          => 'deleted',
        ];
    }

    protected function previewReply(AiConversation $conversation): array
    {
        $payload = [
            'type'              => 'social_post',
            'topic'             => $conversation->topic,
            'description'       => $conversation->description,
            'short_description' => $conversation->short_description,
            'tags'              => $conversation->tags ?? [],
            'images'            => array_map(
                fn (string $path) => ['path' => 'storage/' . $path],
                $conversation->images ?? []
            ),
        ];

        return $this->reply($conversation, $payload, pills: $this->previewPills());
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

    protected function recordUserMessage(AiConversation $conversation, ?string $text, array $imagePaths): void
    {
        if (blank($text) && empty($imagePaths)) {
            return;
        }

        $stored = !empty($imagePaths)
            ? json_encode(['text' => $text, 'images' => $imagePaths])
            : (string) $text;

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'sender'          => 'user',
            'message'         => $stored,
        ]);
    }

    /**
     * @param  string|array<string, mixed>  $message
     */
    protected function reply(AiConversation $conversation, string|array $message, ?array $pills = null, array $extra = []): array
    {
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'sender'          => 'ai',
            'message'         => is_array($message) ? json_encode($message) : $message,
        ]);

        return array_merge([
            'conversation_id' => $conversation->id,
            'message'         => is_array($message) ? null : $message,
            'preview'         => is_array($message) ? $message : null,
            'pills'           => $pills,
            'status'          => $conversation->status,
        ], $extra);
    }
}
