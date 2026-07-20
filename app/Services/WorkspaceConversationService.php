<?php

namespace App\Services;

use App\Exceptions\AIServiceException;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\MatchRecommendation;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\MatchCriteriaService;
use App\Services\AI\PostCuratorService;
use App\Services\AI\ReplyIntentClassifierService;
use App\Services\AI\SocialPostCollectorService;
use Illuminate\Support\Facades\DB;

class WorkspaceConversationService
{
    protected const PILL_APPROVE = 'Approve posting to the feed';
    protected const PILL_EDIT = 'Edit post';
    protected const PILL_DELETE = 'Delete post';
    protected const PILL_MALE = 'Male';
    protected const PILL_FEMALE = 'Female';
    protected const PILL_DATING_PREFERENCE = 'Dating Preference';

    protected const MSG_SELECT_PROMPT = 'Select one of the optional prompts below';
    protected const MSG_UNDER_DEV = 'This feature is currently under development and will be available soon.';
    protected const MSG_SOCIAL_OPENING = 'What is your post about?';
    protected const MSG_SOCIAL_DETAILS_PROMPT = 'Please give me a description and a photo for your post.';
    protected const MSG_STILL_NEED_IMAGE = "Don't forget to share a photo so I can finish your post!";
    protected const MSG_NEED_MORE_DETAIL = 'Your description is a bit short — could you share more detail so I can write a great post? Aim for at least 150 words.';
    protected const MSG_DESCRIPTION_NOT_SUBSTANTIVE = "That description doesn't seem to say much about your post — could you share more specific details about what it's actually about?";
    protected const MSG_PUBLISHED = 'Your post has been published successfully.';
    protected const MSG_DRAFT_DELETED = 'Draft deleted successfully.';
    protected const MSG_ASK_EDIT_INSTRUCTION = 'What would you like to change about your post?';
    protected const MSG_CONVERSATION_DONE = 'This conversation has already been completed. Start a new conversation to create another post.';
    protected const MSG_CHOOSE_OPTION = 'Please choose one of the options below.';
    protected const MIN_DESCRIPTION_WORDS = 150;

    protected const MSG_PROFILE_INCOMPLETE = "You haven't completed your dating preference yet, so I can't use it to find matches. Please choose one of the options below instead, or complete your profile first.";
    protected const MSG_ASK_GENDER = 'Hello! What are you looking for?';
    protected const MSG_ASK_GENDER_AGAIN = "Sorry, I didn't catch that. Please choose one of the options below.";
    protected const MSG_NO_MATCHES = 'No matching found. Please check back again later.';

    protected const MSG_MARKETPLACE_GUIDANCE = 'Let\'s create your advertisement. Please provide the product/service details through the form — image, type, category, link, and discount — then send it over.';
    protected const MSG_AD_NEED_IMAGE = 'Please upload at least one image for your listing.';
    protected const MSG_AD_NEED_TYPE = 'Please choose whether this is a product or a service.';
    protected const MSG_AD_NEED_CATEGORY = 'Please choose a category for your listing.';
    protected const MSG_AD_PUBLISHED = 'Your advertisement has been published successfully.';

    public function __construct(
        protected PostCuratorService $curator,
        protected ReplyIntentClassifierService $replyClassifier,
        protected SocialPostCollectorService $socialCollector,
        protected MatchCriteriaService $matchCriteria,
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
     * @param  array{ad_type?: ?string, category?: ?string, product_url?: ?string, discount_percentage?: ?float, show_sale_badge?: ?bool}  $extra
     *         Market Place ad-form fields, present only while collecting in that workspace.
     * @return array{success: true}
     */
    public function handleMessage(AiConversation $conversation, ?string $text, array $imagePaths, array $extra = []): array
    {
        $this->recordUserMessage($conversation, $text, $imagePaths);

        match ($conversation->status) {
            'idle'                      => $this->handleIdle($conversation, $text),
            'awaiting_match_gender'     => $this->handleAwaitingMatchGender($conversation, $text),
            'awaiting_match_criteria'   => $this->handleAwaitingMatchCriteria($conversation, $text),
            'collecting'                => $this->handleCollecting($conversation, $text, $imagePaths, $extra),
            'preview'                   => $this->handlePreview($conversation, $text),
            'awaiting_edit_instruction' => $this->handleEditInstruction($conversation, $text),
            default                     => $this->storeReply($conversation, self::MSG_CONVERSATION_DONE),
        };

        return ['success' => true];
    }

    // ─── State Handlers (persistence only, no return payloads) ────────────────

    /**
     * Workspace selection is pill-only: an exact match against a workspace's
     * prompt (what a pill click sends) assigns it directly, no confirmation
     * round-trip. Anything else — blank text, or free text that doesn't match
     * any pill — just re-shows the pill list. No AI call is made here.
     */
    protected function handleIdle(AiConversation $conversation, ?string $text): void
    {
        $workspace = $this->matchWorkspaceExact($text);

        if ($workspace) {
            $this->assignWorkspace($conversation, $workspace);
            return;
        }

        $this->storeReply($conversation, self::MSG_SELECT_PROMPT);
        $this->storePills($conversation, $this->activePrompts());
    }

    protected function assignWorkspace(AiConversation $conversation, Workspace $workspace): void
    {
        if (!$workspace->is_supported) {
            $conversation->update(['workspace_id' => null, 'status' => 'idle']);
            $this->storeReply($conversation, self::MSG_UNDER_DEV);
            $this->storePills($conversation, $this->activePrompts());
            return;
        }

        if ($workspace->slug === Workspace::SLUG_MATCHES) {
            $this->enterMatchesWorkspace($conversation, $workspace);
            return;
        }

        if ($workspace->slug === Workspace::SLUG_SOCIAL_POST) {
            $this->enterSocialPostWorkspace($conversation, $workspace);
            return;
        }

        $conversation->update(['workspace_id' => $workspace->id, 'status' => 'collecting']);
        $this->storeReply($conversation, $this->guidanceFor($workspace));
    }

    // ─── Social Post / Market Place Collection ──────────────────────────────

    /**
     * Ask what the post is about and offer 6 freshly AI-generated topic
     * pills as inspiration — the user can tap one or type their own answer.
     */
    protected function enterSocialPostWorkspace(AiConversation $conversation, Workspace $workspace): void
    {
        $conversation->update(['workspace_id' => $workspace->id, 'status' => 'collecting']);
        $this->storeReply($conversation, self::MSG_SOCIAL_OPENING);
        $this->storePills($conversation, $this->socialCollector->suggestTopics());
    }

    protected function handleCollecting(AiConversation $conversation, ?string $text, array $imagePaths, array $extra): void
    {
        $workspace = $conversation->workspace;

        if ($workspace && $workspace->slug === Workspace::SLUG_MARKET_PLACE) {
            $this->handleMarketplaceCollecting($conversation, $text, $imagePaths, $extra);
            return;
        }

        if (blank($conversation->topic)) {
            $this->handleSocialPostTopicHint($conversation, $text, $imagePaths);
            return;
        }

        $this->handleSocialPostCollecting($conversation, $text, $imagePaths);
    }

    /**
     * Phase 1: capture what the post is about. Whatever the user says —
     * whether it's one of the suggested pills or their own free text — is
     * taken at face value as the topic hint, no classification or retry
     * loop. Any image sent alongside is stored for Phase 2, but doesn't by
     * itself satisfy this step; a topic hint is still needed to move on.
     */
    protected function handleSocialPostTopicHint(AiConversation $conversation, ?string $text, array $imagePaths): void
    {
        if (!empty($imagePaths)) {
            $conversation->update(['images' => array_merge($conversation->images ?? [], $imagePaths)]);
        }

        if (blank($text)) {
            $this->storeReply($conversation, self::MSG_SOCIAL_OPENING);
            return;
        }

        $conversation->update(['topic' => trim($text)]);
        $this->storeReply($conversation, self::MSG_SOCIAL_DETAILS_PROMPT);
    }

    /**
     * Phase 2: merge any new images, store any new text as the description,
     * then validate both are present before curating. Both checks are
     * deterministic — no AI call spent on invalid input. The topic hint
     * captured in Phase 1 is passed to the curator as extra context; the
     * post's real topic (used as its title) is still always AI-generated
     * at curation time, overwriting the interim hint.
     */
    protected function handleSocialPostCollecting(AiConversation $conversation, ?string $text, array $imagePaths): void
    {
        if (!empty($imagePaths)) {
            $conversation->update(['images' => array_merge($conversation->images ?? [], $imagePaths)]);
        }

        if (filled($text)) {
            $conversation->update(['description' => trim($text)]);
        }

        if (!$conversation->hasImages()) {
            $this->storeReply($conversation, self::MSG_STILL_NEED_IMAGE);
            return;
        }

        if (!$this->hasValidDescription($conversation)) {
            $this->storeReply($conversation, self::MSG_NEED_MORE_DETAIL);
            return;
        }

        if (!$this->socialCollector->isSubstantive($conversation->description)) {
            $this->storeReply($conversation, self::MSG_DESCRIPTION_NOT_SUBSTANTIVE);
            return;
        }

        try {
            $result = $this->curator->curate($conversation->description, $conversation->images, $conversation->topic);
        } catch (AIServiceException $e) {
            $this->storeReply($conversation, $e->getMessage());
            return;
        }

        $this->finalizePost($conversation, $result);
    }

    /**
     * Deterministic minimum-effort check — catches blank/near-blank
     * submissions before spending an AI call. Not a quality/coherence
     * judgment (that would require its own AI call, deliberately out of
     * scope for this check).
     */
    protected function hasValidDescription(AiConversation $conversation): bool
    {
        $description = trim((string) $conversation->description);

        return $description !== '' && str_word_count($description) >= self::MIN_DESCRIPTION_WORDS;
    }

    /**
     * Shared tail for Social Post curation: persist the curated draft, narrate
     * what the AI understood, then show the preview card and its pills.
     * `description` is deliberately left untouched — it's the user's own
     * words, already stored during collection; only topic (title),
     * short_description, and tags are AI-generated.
     */
    protected function finalizePost(AiConversation $conversation, array $result): void
    {
        $conversation->update([
            'topic'             => $result['topic'],
            'short_description' => $result['short_description'],
            'tags'              => $result['tags'],
            'status'            => 'preview',
        ]);

        $ack = $this->socialCollector->acknowledge($result['topic'], $result['short_description']);
        $this->storeReply($conversation, $ack);

        $this->storePostPreview($conversation);
        $this->storePills($conversation, $this->previewPills());
    }

    /**
     * @param  array{ad_type?: ?string, category?: ?string, product_url?: ?string, discount_percentage?: ?float, show_sale_badge?: ?bool}  $extra
     */
    protected function handleMarketplaceCollecting(AiConversation $conversation, ?string $text, array $imagePaths, array $extra): void
    {
        $images = $conversation->images ?? [];

        if (!empty($imagePaths)) {
            $images = array_merge($images, $imagePaths);
        }

        $adType             = $extra['ad_type'] ?? $conversation->ad_type;
        $category           = $extra['category'] ?? $conversation->category;
        $productUrl         = $extra['product_url'] ?? $conversation->product_url;
        $discountPercentage = $extra['discount_percentage'] ?? $conversation->discount_percentage;
        $showSaleBadge      = $extra['show_sale_badge'] ?? $conversation->show_sale_badge ?? false;
        $note               = filled($text) ? trim($text) : $conversation->image_description;

        $conversation->update([
            'images'              => $images,
            'ad_type'             => $adType,
            'category'            => $category,
            'product_url'         => $productUrl,
            'discount_percentage' => $discountPercentage,
            'show_sale_badge'     => $showSaleBadge,
            'image_description'   => $note,
        ]);

        if (empty($images)) {
            $this->storeReply($conversation, self::MSG_AD_NEED_IMAGE);
            return;
        }

        if (blank($adType)) {
            $this->storeReply($conversation, self::MSG_AD_NEED_TYPE);
            return;
        }

        if (blank($category)) {
            $this->storeReply($conversation, self::MSG_AD_NEED_CATEGORY);
            return;
        }

        try {
            $result = $this->curator->curateAd(
                $adType,
                $category,
                $productUrl,
                $discountPercentage !== null ? (float) $discountPercentage : null,
                $note,
                $images
            );
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

        $this->storeAdPreview($conversation);
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

        $this->storePreview($conversation);
        $this->storePills($conversation, $this->previewPills());
    }

    /**
     * Dispatch to the right preview card renderer for the conversation's workspace.
     */
    protected function storePreview(AiConversation $conversation): void
    {
        $workspace = $conversation->workspace;

        if ($workspace && $workspace->slug === Workspace::SLUG_MARKET_PLACE) {
            $this->storeAdPreview($conversation);
            return;
        }

        $this->storePostPreview($conversation);
    }

    protected function approve(AiConversation $conversation): void
    {
        $workspace = $conversation->workspace;

        if ($workspace && $workspace->slug === Workspace::SLUG_MARKET_PLACE) {
            $this->approveAd($conversation);
            return;
        }

        DB::transaction(function () use ($conversation) {
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
        });

        $this->storeReply($conversation, self::MSG_PUBLISHED);
    }

    protected function approveAd(AiConversation $conversation): void
    {
        DB::transaction(function () use ($conversation) {
            $post = Post::create([
                'user_id'             => $conversation->user_id,
                'workspace_id'        => $conversation->workspace_id,
                'topic'               => $conversation->topic,
                'type'                => 'ad',
                'created_by'          => 'user',
                'content'             => $conversation->description,
                'short_description'   => $conversation->short_description,
                'image_description'   => $conversation->image_description,
                'tags'                => $conversation->tags ?? [],
                'category'            => $conversation->category,
                'ad_type'             => $conversation->ad_type,
                'product_url'         => $conversation->product_url,
                'discount_percentage' => $conversation->discount_percentage,
                'show_sale_badge'     => $conversation->show_sale_badge ?? false,
                'visibility'          => 'public',
                'status'              => 'published',
                'published_at'        => now(),
            ]);

            foreach (($conversation->images ?? []) as $index => $path) {
                $post->images()->create(['image_path' => $path, 'sort_order' => $index]);
            }

            $conversation->update(['status' => 'published', 'post_id' => $post->id]);
        });

        $this->storeReply($conversation, self::MSG_AD_PUBLISHED);
    }

    protected function deleteDraft(AiConversation $conversation): void
    {
        $conversation->delete();

        // No messages to store — the conversation is deleted.
        // The frontend will receive a success response and can redirect.
    }

    // ─── Matches ─────────────────────────────────────────────────────────────

    /**
     * Always asks the 3-way choice (Male / Female / Dating Preference) —
     * never auto-resolves from a saved preference silently. The saved
     * preference is only used when the user explicitly asks for it.
     */
    protected function enterMatchesWorkspace(AiConversation $conversation, Workspace $workspace): void
    {
        $conversation->update(['workspace_id' => $workspace->id, 'status' => 'awaiting_match_gender']);
        $this->storeReply($conversation, self::MSG_ASK_GENDER);
        $this->storePills($conversation, $this->genderPills());
    }

    protected function handleAwaitingMatchGender(AiConversation $conversation, ?string $text): void
    {
        if ($this->isDatingPreferenceChoice($text)) {
            $this->searchUsingDatingPreference($conversation);
            return;
        }

        $gender = $this->replyClassifier->classifyGender((string) $text);

        if (!$gender) {
            $this->storeReply($conversation, self::MSG_ASK_GENDER_AGAIN);
            $this->storePills($conversation, $this->genderPills());
            return;
        }

        $conversation->update(['match_gender' => $gender, 'status' => 'awaiting_match_criteria']);
        $this->storeReply($conversation, $this->askMatchCriteriaMessage($gender));
    }

    /**
     * "Dating Preference" choice: use the user's saved DatingPreference row
     * — gender from `interested_in`, ranking criteria from whatever's set in
     * `partner_preferences`/`deal_breakers` — instead of asking them to
     * describe what they're looking for. Bounces back to the same 3-way
     * choice, still inside the Matches workspace, if their profile isn't
     * complete enough to have a saved preference to use.
     */
    protected function searchUsingDatingPreference(AiConversation $conversation): void
    {
        /** @var User $user */
        $user = $conversation->user()->first();

        if (!$user || !$user->hasCompletedDatingProfile()) {
            $this->storeReply($conversation, self::MSG_PROFILE_INCOMPLETE);
            $this->storePills($conversation, $this->genderPills());
            return;
        }

        $preference = $user->datingPreference;
        $gender     = $preference->interested_in;
        $criteria   = trim(implode('. ', array_filter([
            $preference->partner_preferences,
            $preference->deal_breakers,
        ])));

        $conversation->update([
            'match_gender'   => $gender,
            'match_criteria' => $criteria !== '' ? $criteria : null,
        ]);

        $this->searchMatches($conversation, $gender, $criteria !== '' ? $criteria : null);
    }

    /**
     * Asks once for more specifics if the first answer is too vague to rank
     * with (a ranking boost, not a hard gate — a second vague reply still
     * proceeds). Detected via whether match_criteria is already set: it's
     * only ever set here, after a first answer, so a second call with it
     * already present means this is the follow-up round.
     */
    protected function handleAwaitingMatchCriteria(AiConversation $conversation, ?string $text): void
    {
        if (blank($text)) {
            $this->storeReply($conversation, $this->askMatchCriteriaMessage((string) $conversation->match_gender));
            return;
        }

        $criteria         = trim($text);
        $alreadyAskedOnce = filled($conversation->match_criteria);

        if (!$alreadyAskedOnce && !$this->matchCriteria->isConcrete($criteria)) {
            $conversation->update(['match_criteria' => $criteria]);
            $this->storeReply(
                $conversation,
                sprintf('Could you be a bit more specific about "%s"? For example, an exact trait or number would help.', $criteria)
            );
            return;
        }

        $conversation->update(['match_criteria' => $criteria]);
        $this->searchMatches($conversation, $conversation->match_gender, $criteria);
    }

    /**
     * Find candidates by gender, optionally rank them against free-text
     * criteria, persist MatchRecommendation rows, and present the results.
     */
    protected function searchMatches(AiConversation $conversation, string $gender, ?string $criteria): void
    {
        $candidates = $this->findMatchCandidates($conversation->user_id, $gender);

        if ($candidates->isEmpty()) {
            $conversation->update(['status' => 'completed']);
            $this->storeReply($conversation, self::MSG_NO_MATCHES);
            return;
        }

        $rankings = $criteria ? $this->matchCriteria->rankCandidates($criteria, $candidates) : [];
        $rankingsByUserId = collect($rankings)->keyBy('user_id');

        foreach ($candidates as $candidate) {
            $ranking = $rankingsByUserId->get($candidate->id);

            MatchRecommendation::updateOrCreate(
                ['user_id' => $conversation->user_id, 'recommended_user_id' => $candidate->id],
                [
                    'status'              => 'pending',
                    // compatibility_score is NOT NULL (schema default 0) — fall back to 0,
                    // not null, when no ranking exists (no criteria was given).
                    'compatibility_score' => $ranking['score'] ?? 0,
                    'reason'              => $ranking['reason'] ?? null,
                ]
            );
        }

        $conversation->update(['status' => 'completed']);
        $this->storeMatchSuggestions($conversation, $candidates, $rankingsByUserId);
    }

    /**
     * Users whose dating profile is complete, active, and matches the requested
     * gender. 'both' skips the dating_gender filter entirely (matches either).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function findMatchCandidates(int $userId, string $gender, int $limit = 10)
    {
        return User::query()
            ->where('id', '!=', $userId)
            ->where('status', 'active')
            ->whereHas('datingProfile', function ($query) use ($gender) {
                $query->where('is_active', true);

                if ($gender !== 'both') {
                    $query->where('dating_gender', $gender);
                }
            })
            ->with(['datingProfile.images'])
            ->limit($limit)
            ->get();
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
                fn (string $path) => ['path' => $path],
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
     * Store an AI-generated advertisement preview card (Market Place workspace).
     */
    protected function storeAdPreview(AiConversation $conversation): AiMessage
    {
        $payload = [
            'topic'               => $conversation->topic,
            'description'         => $conversation->description,
            'short_description'   => $conversation->short_description,
            'tags'                => $conversation->tags ?? [],
            'ad_type'             => $conversation->ad_type,
            'category'            => $conversation->category,
            'product_url'         => $conversation->product_url,
            'discount_percentage' => $conversation->discount_percentage,
            'show_sale_badge'     => (bool) $conversation->show_sale_badge,
            'images'              => array_map(
                fn (string $path) => ['path' => $path],
                $conversation->images ?? []
            ),
        ];

        return AiMessage::create([
            'conversation_id' => $conversation->id,
            'sender'          => 'ai',
            'type'            => 'ad_preview',
            'message'         => json_encode($payload),
        ]);
    }

    /**
     * Store suggested dating-profile matches (Matches workspace).
     *
     * @param  \Illuminate\Support\Collection<int, User>  $candidates
     * @param  \Illuminate\Support\Collection<int, array{user_id: int, score: int, reason: string}>|null  $rankingsByUserId  keyed by user_id; null/empty when no criteria was given
     */
    protected function storeMatchSuggestions(AiConversation $conversation, $candidates, $rankingsByUserId = null): AiMessage
    {
        $rankingsByUserId = $rankingsByUserId ?? collect();

        $payload = $candidates->map(function (User $candidate) use ($rankingsByUserId) {
            $profile = $candidate->datingProfile;
            $photo   = $profile?->images?->firstWhere('is_primary', true) ?? $profile?->images?->first();
            $ranking = $rankingsByUserId->get($candidate->id);

            return [
                'id'                  => $candidate->id,
                'user_id'             => $candidate->id,
                'avatar'              => asset($candidate->avatar ?? 'user.png'),
                'name'                => $candidate->name,
                'username'            => $candidate->username,
                'city'                => $profile?->dating_location ?? $profile?->city,
                'about'               => $profile?->about ?? $profile?->about_me,
                'photo'               => $photo ? ['path' => $photo->image_path] : null,
                'compatibility_score' => $ranking['score'] ?? null,
                'reason'              => $ranking['reason'] ?? null,
            ];
        })->values()->all();

        return AiMessage::create([
            'conversation_id' => $conversation->id,
            'sender'          => 'ai',
            'type'            => 'matches',
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

    // ─── Pill Helpers ────────────────────────────────────────────────────────

    protected function previewPills(): array
    {
        return [self::PILL_APPROVE, self::PILL_EDIT, self::PILL_DELETE];
    }

    protected function genderPills(): array
    {
        return [self::PILL_MALE, self::PILL_FEMALE, self::PILL_DATING_PREFERENCE];
    }

    /**
     * Exact match against the "Dating Preference" pill's label — deterministic,
     * same pattern as matchWorkspaceExact(), not an AI classification.
     */
    protected function isDatingPreferenceChoice(?string $text): bool
    {
        if (blank($text)) {
            return false;
        }

        return mb_strtolower(trim($text)) === mb_strtolower(self::PILL_DATING_PREFERENCE);
    }

    protected function askMatchCriteriaMessage(string $gender): string
    {
        $label = match ($gender) {
            'male'   => 'men',
            'female' => 'women',
            default  => 'people',
        };

        return "What type of {$label} are you looking for? Describe them a bit.";
    }

    protected function guidanceFor(Workspace $workspace): string
    {
        return match ($workspace->slug) {
            Workspace::SLUG_MARKET_PLACE => self::MSG_MARKETPLACE_GUIDANCE,
            default                      => self::MSG_UNDER_DEV,
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
