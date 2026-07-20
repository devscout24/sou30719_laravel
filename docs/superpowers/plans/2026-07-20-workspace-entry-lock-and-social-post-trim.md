# Workspace Entry Lock & Social Post Flow Trim Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Force workspace selection to happen only via an exact pill click (no LLM-guessed free-text routing), and collapse the Social Post workspace's two-phase "ask topic, then ask details" chat into a single "give me a description + photo" step, per `docs/superpowers/specs/2026-07-20-workspace-entry-lock-and-social-post-trim-design.md`.

**Architecture:** This is an in-place trim of the existing `WorkspaceConversationService` state machine — no new classes, no new status values, no strategy-pattern refactor (deliberately deferred to a future Phase 2). Dead code created by the trim (`WorkspaceIntentClassifierService`, part of `ReplyIntentClassifierService`, part of `SocialPostCollectorService`, the `confirming_workspace` status, the `topic_clarify_attempts` column) is removed rather than left unused.

**Tech Stack:** Laravel (PHP), MySQL, PHPUnit (Feature tests), Eloquent.

## Global Constraints

- `ai_conversations.status` enum after this change: `idle, collecting, preview, awaiting_edit_instruction, awaiting_match_gender, awaiting_match_criteria, completed, published` — `confirming_workspace` removed, no new values added.
- `POST /conversations/{slug}/messages` and `GET /conversations/{slug}` response shapes do not change — this is a behavioral change inside the state machine only.
- Match Finder (`Workspace::SLUG_MATCHES`) and Marketplace (`Workspace::SLUG_MARKET_PLACE`) workspace behavior must remain fully unchanged.
- No per-workspace strategy/interface refactor in this plan — out of scope per spec.
- Tasks must land in the order given below: Task 1 and Task 2 stop all code from referencing `confirming_workspace` / `topic_clarify_attempts` before Task 3 drops them from the schema, so the app is never in a state where code references a column or enum value the database doesn't have.

---

## File Structure

- **Modify** `app/Services/WorkspaceConversationService.php` — remove free-text workspace guessing + `confirming_workspace` handling (Task 1); collapse Social Post's two-phase collection into one step (Task 2).
- **Delete** `app/Services/AI/WorkspaceIntentClassifierService.php` — only caller removed in Task 1 (Task 1).
- **Modify** `app/Services/AI/ReplyIntentClassifierService.php` — remove `classifyConfirmation()` and the vocab constants only it used, now unreachable (Task 1).
- **Modify** `app/Services/AI/SocialPostCollectorService.php` — remove the topic-discovery chat methods, keep `acknowledge()` (Task 2).
- **Create** `database/migrations/2026_07_20_090000_remove_confirming_workspace_and_topic_clarify_attempts.php` (Task 3).
- **Modify** `app/Models/AiConversation.php` — drop `topic_clarify_attempts` from `$fillable`/`$casts` (Task 3).
- **Create** `tests/Feature/AiConversation/WorkspaceEntryFlowTest.php` (Task 1).
- **Create** `tests/Feature/AiConversation/SocialPostCollectionTest.php` (Task 2).
- **Create** `tests/Feature/AiConversation/AiConversationSchemaTest.php` (Task 3).
- **Create** `tests/Feature/AiConversation/WorkspaceRoutingRegressionTest.php` (Task 4).

---

### Task 1: Force pill-only workspace selection at idle

**Files:**
- Modify: `app/Services/WorkspaceConversationService.php:12-59` (imports/constructor), `app/Services/WorkspaceConversationService.php:24-25,39` (constants), `app/Services/WorkspaceConversationService.php:112-197` (`handleMessage`/`handleIdle`/`handleConfirmingWorkspace`), `app/Services/WorkspaceConversationService.php:880-883` (`confirmationPills()`)
- Delete: `app/Services/AI/WorkspaceIntentClassifierService.php`
- Modify: `app/Services/AI/ReplyIntentClassifierService.php:10-46` (remove `classifyConfirmation()` and its vocab constants)
- Test: `tests/Feature/AiConversation/WorkspaceEntryFlowTest.php`

**Interfaces:**
- Consumes: `Workspace::active()`, `Workspace::SLUG_SOCIAL_POST` (existing, unchanged), `WorkspaceConversationService::startConversation(int $userId, ?int $workspaceId = null): array{conversation_id:int, slug:string}` (existing, unchanged), `WorkspaceConversationService::handleMessage(AiConversation $conversation, ?string $text, array $imagePaths, array $extra = []): array{success:true}` (existing, unchanged signature).
- Produces: `handleIdle()` now has exactly two outcomes (assign workspace on exact pill match, or re-show the `MSG_SELECT_PROMPT` + pill list otherwise) — Task 2 and Task 4 rely on this when they exercise other workspaces via the same `idle` entry point.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AiConversation/WorkspaceEntryFlowTest.php`:

```php
<?php

namespace Tests\Feature\AiConversation;

use App\Models\AiConversation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceEntryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSocialPostWorkspace(): Workspace
    {
        return Workspace::create([
            'title'        => 'Social Post',
            'description'  => 'Share a post.',
            'prompt'       => 'Share a post',
            'slug'         => Workspace::SLUG_SOCIAL_POST,
            'is_supported' => true,
            'status'       => 'active',
            'sort_order'   => 1,
        ]);
    }

    public function test_free_text_at_idle_is_ignored_and_pills_are_reshown(): void
    {
        $this->makeSocialPostWorkspace();
        $user = User::factory()->create();

        $service = app(WorkspaceConversationService::class);
        $started = $service->startConversation($user->id);
        $conversation = AiConversation::find($started['conversation_id']);

        $service->handleMessage($conversation, 'I want to make a post about my trip', []);
        $conversation->refresh();

        $this->assertSame('idle', $conversation->status);
        $this->assertNull($conversation->workspace_id);

        $lastPills = $conversation->messages()->where('type', 'pills')->get()->last();
        $this->assertNotNull($lastPills);
        $this->assertSame(['Share a post'], json_decode($lastPills->message, true));
    }

    public function test_exact_pill_text_assigns_workspace_directly_without_confirmation(): void
    {
        $workspace = $this->makeSocialPostWorkspace();
        $user = User::factory()->create();

        $service = app(WorkspaceConversationService::class);
        $started = $service->startConversation($user->id);
        $conversation = AiConversation::find($started['conversation_id']);

        $service->handleMessage($conversation, 'Share a post', []);
        $conversation->refresh();

        $this->assertSame($workspace->id, $conversation->workspace_id);
        $this->assertSame('collecting', $conversation->status);
    }

    public function test_blank_text_at_idle_shows_pills(): void
    {
        $this->makeSocialPostWorkspace();
        $user = User::factory()->create();

        $service = app(WorkspaceConversationService::class);
        $started = $service->startConversation($user->id);
        $conversation = AiConversation::find($started['conversation_id']);

        $service->handleMessage($conversation, null, []);
        $conversation->refresh();

        $this->assertSame('idle', $conversation->status);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=WorkspaceEntryFlowTest`
Expected: FAIL — `test_free_text_at_idle_is_ignored_and_pills_are_reshown` fails because today's `handleIdle()` calls the LLM classifier for unmatched free text instead of re-showing pills (and will error/timeout without a mocked `OpenAIService`, or diverge from the assertion).

- [ ] **Step 3: Remove the `WorkspaceIntentClassifierService` import and constructor dependency**

In `app/Services/WorkspaceConversationService.php`, remove the import (currently line 16):

```php
use App\Services\AI\WorkspaceIntentClassifierService;
```

And remove the constructor parameter (currently line 54 of the constructor block):

```php
    public function __construct(
        protected PostCuratorService $curator,
        protected WorkspaceIntentClassifierService $classifier,
        protected ReplyIntentClassifierService $replyClassifier,
        protected SocialPostCollectorService $socialCollector,
        protected MatchCriteriaService $matchCriteria,
    ) {
    }
```

becomes:

```php
    public function __construct(
        protected PostCuratorService $curator,
        protected ReplyIntentClassifierService $replyClassifier,
        protected SocialPostCollectorService $socialCollector,
        protected MatchCriteriaService $matchCriteria,
    ) {
    }
```

- [ ] **Step 4: Remove the now-dead constants**

Remove these two lines from the constants block (currently lines 24-25):

```php
    protected const PILL_CONFIRM_YES = "Yes, that's right";
    protected const PILL_CONFIRM_NO = 'No, something else';
```

Remove this line (currently line 39):

```php
    protected const MSG_CLARIFY_INTENT = "I couldn't quite tell what you're looking to do. Could you be more specific, or choose one of the options below?";
```

- [ ] **Step 5: Simplify `handleIdle()` and remove `handleConfirmingWorkspace()`**

Replace (currently lines 132-197):

```php
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

        $conversation->update([
            'workspace_id'   => $result['workspace']->id,
            'status'         => 'confirming_workspace',
            'match_gender'   => $result['match_gender'],
            'match_criteria' => $result['match_criteria'],
        ]);

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
                $conversation->update(['workspace_id' => null, 'status' => 'idle', 'match_gender' => null, 'match_criteria' => null]);
                $this->storeReply($conversation, self::MSG_CLARIFY_INTENT);
                $this->storePills($conversation, $this->activePrompts());
                return;
            }

            $this->assignWorkspace($conversation, $workspace);
            return;
        }

        $conversation->update(['workspace_id' => null, 'status' => 'idle', 'match_gender' => null, 'match_criteria' => null]);

        if ($decision === 'no') {
            $this->storeReply($conversation, self::MSG_CLARIFY_INTENT);
            $this->storePills($conversation, $this->activePrompts());
            return;
        }

        // Unclear reply — treat it as a fresh attempt to describe their intent.
        $this->handleIdle($conversation, $text);
    }
```

with:

```php
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
```

- [ ] **Step 6: Remove the `confirming_workspace` arm from `handleMessage()`**

In `handleMessage()` (currently lines 112-128), remove this line from the `match` statement:

```php
            'confirming_workspace'      => $this->handleConfirmingWorkspace($conversation, $text),
```

- [ ] **Step 7: Remove `confirmationPills()`**

Remove (currently lines 880-883):

```php
    protected function confirmationPills(): array
    {
        return [self::PILL_CONFIRM_YES, self::PILL_CONFIRM_NO];
    }

```

- [ ] **Step 8: Delete the now-unused `WorkspaceIntentClassifierService`**

```bash
rm app/Services/AI/WorkspaceIntentClassifierService.php
```

- [ ] **Step 9: Remove the now-dead `classifyConfirmation()` from `ReplyIntentClassifierService`**

`classifyConfirmation()`'s only caller was `handleConfirmingWorkspace()`, just deleted in Step 5. Confirm no other caller exists before removing it:

Run: `grep -rn "classifyConfirmation" app/`
Expected: exactly one match — the method's own definition line in `app/Services/AI/ReplyIntentClassifierService.php`. No call sites anywhere else.

In `app/Services/AI/ReplyIntentClassifierService.php`, remove the `classifyConfirmation()` method (currently lines 24-46):

```php
    /**
     * Interpret a free-text reply to a yes/no confirmation question.
     *
     * @return 'yes'|'no'|null
     */
    public function classifyConfirmation(string $text): ?string
    {
        if (blank($text)) {
            return null;
        }

        $words = $this->normalize($text);

        if ($this->matchesAny($words, self::DECLINE_WORDS)) {
            return 'no';
        }

        if ($this->matchesAny($words, self::CONFIRM_WORDS)) {
            return 'yes';
        }

        return $this->aiClassify($text, ['yes', 'no']);
    }

```

And remove the two constants only that method used (currently lines 10-11):

```php
    protected const CONFIRM_WORDS = ['yes', 'yeah', 'yep', 'yup', 'sure', 'correct', 'confirm', 'ok', 'okay', 'affirmative', 'thats right'];
    protected const DECLINE_WORDS = ['no', 'nope', 'nah', 'incorrect', 'wrong', 'negative', 'not right', 'thats wrong'];
```

`normalize()`, `matchesAny()`, `aiClassify()`, `NEGATION_WORDS`, and the other two classify methods (`classifyPreviewAction()`, `classifyGender()`) are still used elsewhere in `WorkspaceConversationService` — leave them as-is.

- [ ] **Step 10: Run tests to verify they pass**

Run: `php artisan test --filter=WorkspaceEntryFlowTest`
Expected: PASS (3 tests, 0 failures)

- [ ] **Step 11: Run the full test suite to check for regressions**

Run: `php artisan test`
Expected: PASS. (No other test currently references `WorkspaceIntentClassifierService`, `confirming_workspace`, or `classifyConfirmation` — confirmed via `grep -rn "WorkspaceIntentClassifierService\|confirming_workspace\|classifyConfirmation" tests/` returning no matches before this task started.)

- [ ] **Step 12: Commit**

```bash
git add app/Services/WorkspaceConversationService.php app/Services/AI/ReplyIntentClassifierService.php tests/Feature/AiConversation/WorkspaceEntryFlowTest.php
git rm app/Services/AI/WorkspaceIntentClassifierService.php
git commit -m "feat: force pill-only workspace selection, remove free-text guessing"
```

---

### Task 2: Collapse Social Post collection into a single description+photo step

**Files:**
- Modify: `app/Services/WorkspaceConversationService.php:29-39` (constants), `app/Services/WorkspaceConversationService.php:219-346` (`handleCollecting` through `finalizePost`)
- Modify: `app/Services/AI/SocialPostCollectorService.php` (remove topic-discovery methods, keep `acknowledge()`)
- Test: `tests/Feature/AiConversation/SocialPostCollectionTest.php`

**Interfaces:**
- Consumes: `PostCuratorService::curate(string $description, array $imagePaths): array{topic:string, description:string, short_description:string, tags:string[]}` (existing, unchanged), `AiConversation::hasImages(): bool` (existing, unchanged), `SocialPostCollectorService::acknowledge(string $topic, string $shortDescription): string` (existing, unchanged), `handleIdle()`'s pill-only entry from Task 1 (used to reach `collecting` in tests).
- Produces: `WorkspaceConversationService::handleSocialPostCollecting(AiConversation $conversation, ?string $text, array $imagePaths): void` — new protected method, called only from `handleCollecting()`. `WorkspaceConversationService::hasValidDescription(AiConversation $conversation): bool` — new protected method.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AiConversation/SocialPostCollectionTest.php`:

```php
<?php

namespace Tests\Feature\AiConversation;

use App\Models\AiConversation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\PostCuratorService;
use App\Services\AI\SocialPostCollectorService;
use App\Services\WorkspaceConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialPostCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function startSocialPostConversation(): AiConversation
    {
        Workspace::create([
            'title'        => 'Social Post',
            'description'  => 'Share a post.',
            'prompt'       => 'Share a post',
            'slug'         => Workspace::SLUG_SOCIAL_POST,
            'is_supported' => true,
            'status'       => 'active',
            'sort_order'   => 1,
        ]);
        $user = User::factory()->create();

        $service = app(WorkspaceConversationService::class);
        $started = $service->startConversation($user->id);
        $conversation = AiConversation::find($started['conversation_id']);

        $service->handleMessage($conversation, 'Share a post', []);

        return $conversation->refresh();
    }

    public function test_image_without_description_asks_for_description(): void
    {
        $conversation = $this->startSocialPostConversation();
        $service = app(WorkspaceConversationService::class);

        $service->handleMessage($conversation, null, ['posts/photo1.jpg']);
        $conversation->refresh();

        $this->assertSame('collecting', $conversation->status);
        $this->assertSame(['posts/photo1.jpg'], $conversation->images);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('bit more detail', $lastMessage->message);
    }

    public function test_short_description_without_image_asks_for_image_first(): void
    {
        $conversation = $this->startSocialPostConversation();
        $service = app(WorkspaceConversationService::class);

        $service->handleMessage($conversation, 'hi', []);
        $conversation->refresh();

        $this->assertSame('collecting', $conversation->status);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('photo', $lastMessage->message);
    }

    public function test_too_short_description_with_image_is_rejected(): void
    {
        $conversation = $this->startSocialPostConversation();
        $service = app(WorkspaceConversationService::class);

        $service->handleMessage($conversation, 'hi there', ['posts/photo1.jpg']);
        $conversation->refresh();

        $this->assertSame('collecting', $conversation->status);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('bit more detail', $lastMessage->message);
    }

    public function test_valid_description_and_image_curates_and_moves_to_preview(): void
    {
        $conversation = $this->startSocialPostConversation();

        $this->mock(PostCuratorService::class, function ($mock) {
            $mock->shouldReceive('curate')
                ->once()
                ->with('A lovely sunset over the bay', ['posts/photo1.jpg'])
                ->andReturn([
                    'topic'             => 'Sunset',
                    'description'       => 'A lovely sunset over the bay, painted gold and pink.',
                    'short_description' => 'A gorgeous sunset over the bay.',
                    'tags'              => ['sunset', 'bay', 'evening'],
                ]);
        });

        // finalizePost() also calls SocialPostCollectorService::acknowledge(),
        // which otherwise makes a real OpenAI HTTP call — mock it too.
        $this->mock(SocialPostCollectorService::class, function ($mock) {
            $mock->shouldReceive('acknowledge')
                ->once()
                ->with('Sunset', 'A gorgeous sunset over the bay.')
                ->andReturn('Got it — putting your sunset post together now!');
        });

        $service = app(WorkspaceConversationService::class);
        $service->handleMessage($conversation, 'A lovely sunset over the bay', ['posts/photo1.jpg']);
        $conversation->refresh();

        $this->assertSame('preview', $conversation->status);
        $this->assertSame('Sunset', $conversation->topic);
        $this->assertSame(['sunset', 'bay', 'evening'], $conversation->tags);

        $preview = $conversation->messages()->where('type', 'post')->get()->last();
        $this->assertNotNull($preview);

        $pills = $conversation->messages()->where('type', 'pills')->get()->last();
        $this->assertSame(['Approve posting to the feed', 'Edit post', 'Delete post'], json_decode($pills->message, true));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SocialPostCollectionTest`
Expected: FAIL — today's flow still asks "what would you like to post about" as a topic-discovery chat step (via the real `SocialPostCollectorService::classifyTopic()` LLM call), so these single-step assertions don't hold yet.

- [ ] **Step 3: Update the Social Post opening message and add new constants**

Replace the constants block (currently lines 29-39, already missing `MSG_CLARIFY_INTENT` after Task 1):

```php
    protected const MSG_SELECT_PROMPT = 'Select one of the optional prompts below';
    protected const MSG_UNDER_DEV = 'This feature is currently under development and will be available soon.';
    protected const MSG_SOCIAL_OPENING = 'Hi! What would you like to post about today?';
    protected const MSG_TOPIC_FALLBACK = "No worries — just share a photo of what you'd like to post, and I'll take it from there.";
    protected const MSG_STILL_NEED_IMAGE = "Don't forget to share a photo so I can finish your post!";
    protected const MSG_PUBLISHED = 'Your post has been published successfully.';
    protected const MSG_DRAFT_DELETED = 'Draft deleted successfully.';
    protected const MSG_ASK_EDIT_INSTRUCTION = 'What would you like to change about your post?';
    protected const MSG_CONVERSATION_DONE = 'This conversation has already been completed. Start a new conversation to create another post.';
    protected const MSG_CHOOSE_OPTION = 'Please choose one of the options below.';
```

with:

```php
    protected const MSG_SELECT_PROMPT = 'Select one of the optional prompts below';
    protected const MSG_UNDER_DEV = 'This feature is currently under development and will be available soon.';
    protected const MSG_SOCIAL_OPENING = 'Tell me about your post and share a photo to go with it.';
    protected const MSG_STILL_NEED_IMAGE = "Don't forget to share a photo so I can finish your post!";
    protected const MSG_NEED_MORE_DETAIL = 'Could you share a bit more detail about your post?';
    protected const MSG_PUBLISHED = 'Your post has been published successfully.';
    protected const MSG_DRAFT_DELETED = 'Draft deleted successfully.';
    protected const MSG_ASK_EDIT_INSTRUCTION = 'What would you like to change about your post?';
    protected const MSG_CONVERSATION_DONE = 'This conversation has already been completed. Start a new conversation to create another post.';
    protected const MSG_CHOOSE_OPTION = 'Please choose one of the options below.';
    protected const MIN_DESCRIPTION_WORDS = 3;
```

- [ ] **Step 4: Replace the Social Post collection methods**

Replace (currently lines 219-346, from `handleCollecting()` through the end of `finalizePost()`):

```php
    protected function handleCollecting(AiConversation $conversation, ?string $text, array $imagePaths, array $extra): void
    {
        $workspace = $conversation->workspace;

        if ($workspace && $workspace->slug === Workspace::SLUG_MARKET_PLACE) {
            $this->handleMarketplaceCollecting($conversation, $text, $imagePaths, $extra);
            return;
        }

        if (!empty($imagePaths)) {
            $conversation->update(['images' => array_merge($conversation->images ?? [], $imagePaths)]);
        }

        if (blank($conversation->topic)) {
            $this->handleTopicDiscovery($conversation, $text);
            return;
        }

        $this->handleDetailsCollection($conversation, $text);
    }

    /**
     * Phase 1: figure out what the user wants to post about. An uploaded image
     * (with or without accompanying text) is content enough to skip straight to
     * curation — vision fills in what words didn't. Otherwise classify the text;
     * an unclear reply asks again (varied, AI-generated) up to 3 times, then
     * pivots to asking directly for a photo instead of repeating the question.
     */
    protected function handleTopicDiscovery(AiConversation $conversation, ?string $text): void
    {
        if ($conversation->hasImages()) {
            $this->curateFromImage($conversation, $text);
            return;
        }

        if (blank($text)) {
            $this->storeReply($conversation, self::MSG_SOCIAL_OPENING);
            return;
        }

        $result = $this->socialCollector->classifyTopic($text, $this->recentHistory($conversation));

        if (blank($result['topic'])) {
            $attempts = ($conversation->topic_clarify_attempts ?? 0) + 1;
            $conversation->update(['topic_clarify_attempts' => $attempts]);

            $reply = $attempts >= 3 ? self::MSG_TOPIC_FALLBACK : ($result['reply'] ?: self::MSG_TOPIC_FALLBACK);
            $this->storeReply($conversation, $reply);
            return;
        }

        $conversation->update(['topic' => $result['topic'], 'topic_clarify_attempts' => null]);

        $reply = $this->socialCollector->askForDetails($result['topic']);
        $this->storeReply($conversation, $reply);
    }

    /**
     * Phase 2: topic is known, waiting on a photo (description/elaboration is
     * optional). Any text is stored as elaboration; once an image exists, curate.
     */
    protected function handleDetailsCollection(AiConversation $conversation, ?string $text): void
    {
        if (filled($text)) {
            $conversation->update(['description' => trim($text)]);
        }

        if ($conversation->hasImages()) {
            $this->curateNow($conversation);
            return;
        }

        $this->storeReply($conversation, self::MSG_STILL_NEED_IMAGE);
    }

    /**
     * Curate straight from an image (Phase 1 image-first path) — topic doesn't
     * exist yet, so an empty description is fine; curate() leans on the image.
     */
    protected function curateFromImage(AiConversation $conversation, ?string $text): void
    {
        try {
            $result = $this->curator->curate($text ? trim($text) : '', $conversation->images);
        } catch (AIServiceException $e) {
            $this->storeReply($conversation, $e->getMessage());
            return;
        }

        $this->finalizePost($conversation, $result);
    }

    /**
     * Curate once topic + image are both already known (Phase 2 completion).
     * Falls back to the bare topic as the description when the user never
     * added elaboration text — vision carries the rest.
     */
    protected function curateNow(AiConversation $conversation): void
    {
        try {
            $result = $this->curator->curate($conversation->description ?: $conversation->topic, $conversation->images);
        } catch (AIServiceException $e) {
            $this->storeReply($conversation, $e->getMessage());
            return;
        }

        $this->finalizePost($conversation, $result);
    }

    /**
     * Shared tail for both curation paths: persist the curated draft, narrate
     * what the AI understood, then show the preview card and its pills.
     */
    protected function finalizePost(AiConversation $conversation, array $result): void
    {
        $conversation->update([
            'topic'             => $result['topic'],
            'description'       => $result['description'],
            'short_description' => $result['short_description'],
            'tags'              => $result['tags'],
            'status'            => 'preview',
        ]);

        $ack = $this->socialCollector->acknowledge($result['topic'], $result['short_description']);
        $this->storeReply($conversation, $ack);

        $this->storePostPreview($conversation);
        $this->storePills($conversation, $this->previewPills());
    }
```

with:

```php
    protected function handleCollecting(AiConversation $conversation, ?string $text, array $imagePaths, array $extra): void
    {
        $workspace = $conversation->workspace;

        if ($workspace && $workspace->slug === Workspace::SLUG_MARKET_PLACE) {
            $this->handleMarketplaceCollecting($conversation, $text, $imagePaths, $extra);
            return;
        }

        $this->handleSocialPostCollecting($conversation, $text, $imagePaths);
    }

    /**
     * Single-step Social Post collection: merge any new images, store any new
     * text as the description, then validate both are present before curating.
     * Both checks are deterministic — no AI call spent on invalid input. The
     * post's topic (used as its title) is always AI-generated at curation
     * time, never asked of the user as a separate step.
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

        try {
            $result = $this->curator->curate($conversation->description, $conversation->images);
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
     */
    protected function finalizePost(AiConversation $conversation, array $result): void
    {
        $conversation->update([
            'topic'             => $result['topic'],
            'description'       => $result['description'],
            'short_description' => $result['short_description'],
            'tags'              => $result['tags'],
            'status'            => 'preview',
        ]);

        $ack = $this->socialCollector->acknowledge($result['topic'], $result['short_description']);
        $this->storeReply($conversation, $ack);

        $this->storePostPreview($conversation);
        $this->storePills($conversation, $this->previewPills());
    }
```

- [ ] **Step 5: Replace `SocialPostCollectorService` with the trimmed version**

Replace the entire contents of `app/Services/AI/SocialPostCollectorService.php`:

```php
<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use Illuminate\Support\Facades\Log;

class SocialPostCollectorService
{
    protected const FALLBACK_ACK_REPLY = "Got it! Here's what I put together:";

    public function __construct(protected OpenAIService $openai)
    {
    }

    /**
     * Short narration shown right before the preview card, so the transition
     * from chat to structured card doesn't feel like a silent robotic dump.
     */
    public function acknowledge(string $topic, string $shortDescription): string
    {
        $messages = [
            [
                'role'    => 'system',
                'content' => 'You are a friendly assistant inside the "Social" workspace of a social app. '
                    . 'The user just finished describing (in words and/or a photo) a post about "' . $topic . '". '
                    . 'Write one short, warm sentence (max 20 words) narrating that you understood it and are '
                    . 'putting the post together now. No markdown, no surrounding quotes, just the sentence itself.',
            ],
            [
                'role'    => 'user',
                'content' => "Short summary of the post: {$shortDescription}",
            ],
        ];

        try {
            $content = $this->openai->chat($messages);
        } catch (AIServiceException $e) {
            Log::warning('Social post acknowledgment failed', ['error' => $e->getMessage()]);

            return self::FALLBACK_ACK_REPLY;
        }

        $reply = trim($content);

        return $reply !== '' ? $reply : self::FALLBACK_ACK_REPLY;
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=SocialPostCollectionTest`
Expected: PASS (4 tests, 0 failures)

- [ ] **Step 7: Run the full test suite to check for regressions**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/WorkspaceConversationService.php app/Services/AI/SocialPostCollectorService.php tests/Feature/AiConversation/SocialPostCollectionTest.php
git commit -m "feat: collapse Social Post collection into one description+photo step"
```

---

### Task 3: Drop `confirming_workspace` status and `topic_clarify_attempts` column

**Files:**
- Create: `database/migrations/2026_07_20_090000_remove_confirming_workspace_and_topic_clarify_attempts.php`
- Modify: `app/Models/AiConversation.php:12-42` (`$fillable`/`$casts`)
- Test: `tests/Feature/AiConversation/AiConversationSchemaTest.php`

**Interfaces:**
- Consumes: nothing from Tasks 1-2 directly — this is a pure schema catch-up now that no code references either the `confirming_workspace` enum value or the `topic_clarify_attempts` column (both removed by end of Task 2).
- Produces: nothing new consumed by later tasks — this is the terminal cleanup for the entry-flow/Social-Post trim.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AiConversation/AiConversationSchemaTest.php`:

```php
<?php

namespace Tests\Feature\AiConversation;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AiConversationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_topic_clarify_attempts_column_is_removed(): void
    {
        $this->assertFalse(Schema::hasColumn('ai_conversations', 'topic_clarify_attempts'));
    }

    public function test_confirming_workspace_status_is_no_longer_a_valid_enum_value(): void
    {
        $user = User::factory()->create();
        $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'idle']);

        $this->expectException(QueryException::class);

        $conversation->update(['status' => 'confirming_workspace']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AiConversationSchemaTest`
Expected: FAIL — `topic_clarify_attempts` still exists, and `confirming_workspace` is still a valid enum value today (no exception thrown).

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_20_090000_remove_confirming_workspace_and_topic_clarify_attempts.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free-text workspace guessing (and its "did you mean X?" confirmation
     * round-trip) was removed — workspace selection is pill-only now, so
     * confirming_workspace is unreachable. Social Post's topic-discovery chat
     * step was also removed (topic is now always AI-generated from the
     * description+image at curation time), so its retry counter goes too.
     */
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('topic_clarify_attempts');
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->enum('status', [
                'idle',
                'collecting',
                'preview',
                'awaiting_edit_instruction',
                'awaiting_match_gender',
                'awaiting_match_criteria',
                'completed',
                'published',
            ])->default('idle')->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->enum('status', [
                'idle',
                'confirming_workspace',
                'collecting',
                'preview',
                'awaiting_edit_instruction',
                'awaiting_match_gender',
                'awaiting_match_criteria',
                'completed',
                'published',
            ])->default('idle')->change();
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->unsignedTinyInteger('topic_clarify_attempts')->nullable()->default(0)->after('topic');
        });
    }
};
```

- [ ] **Step 4: Update the `AiConversation` model**

In `app/Models/AiConversation.php`, remove `'topic_clarify_attempts',` from `$fillable` (currently line 19) and `'topic_clarify_attempts'  => 'integer',` from `$casts` (currently line 36).

- [ ] **Step 5: Run the migration**

Run: `php artisan migrate`
Expected: the new migration runs successfully with no errors.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=AiConversationSchemaTest`
Expected: PASS (2 tests, 0 failures)

- [ ] **Step 7: Run the full test suite to check for regressions**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_07_20_090000_remove_confirming_workspace_and_topic_clarify_attempts.php app/Models/AiConversation.php tests/Feature/AiConversation/AiConversationSchemaTest.php
git commit -m "chore: drop confirming_workspace status and topic_clarify_attempts column"
```

---

### Task 4: Regression coverage for Matches and Marketplace pill routing

**Files:**
- Test: `tests/Feature/AiConversation/WorkspaceRoutingRegressionTest.php`

**Interfaces:**
- Consumes: `WorkspaceConversationService::startConversation()`/`handleMessage()` (unchanged from Task 1), `Workspace::SLUG_MATCHES`/`SLUG_MARKET_PLACE` (existing), `User::hasCompletedDatingProfile()` (existing, unchanged — confirmed to return `false` when no `DatingProfile`/`DatingPreference` rows exist, which is exactly what this test relies on to prove the Matches branch of `assignWorkspace()` was reached).
- Produces: nothing — this task is test-only, no production code changes. Since Matches and Marketplace are out of scope for this change, these tests are expected to pass immediately (they exercise code paths Tasks 1-3 didn't touch) — the deliverable is the regression guard itself, not new passing behavior.

- [ ] **Step 1: Write the tests**

Create `tests/Feature/AiConversation/WorkspaceRoutingRegressionTest.php`:

```php
<?php

namespace Tests\Feature\AiConversation;

use App\Models\AiConversation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceRoutingRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_pill_assigns_workspace_and_enters_collecting(): void
    {
        $workspace = Workspace::create([
            'title'        => 'Market Place',
            'description'  => 'Sell something.',
            'prompt'       => 'Sell something',
            'slug'         => Workspace::SLUG_MARKET_PLACE,
            'is_supported' => true,
            'status'       => 'active',
            'sort_order'   => 1,
        ]);
        $user = User::factory()->create();

        $service = app(WorkspaceConversationService::class);
        $started = $service->startConversation($user->id);
        $conversation = AiConversation::find($started['conversation_id']);

        $service->handleMessage($conversation, 'Sell something', []);
        $conversation->refresh();

        $this->assertSame($workspace->id, $conversation->workspace_id);
        $this->assertSame('collecting', $conversation->status);
    }

    public function test_matches_pill_routes_through_matches_workspace(): void
    {
        Workspace::create([
            'title'        => 'Matches',
            'description'  => 'Find a match.',
            'prompt'       => 'Find a match',
            'slug'         => Workspace::SLUG_MATCHES,
            'is_supported' => true,
            'status'       => 'active',
            'sort_order'   => 1,
        ]);
        $user = User::factory()->create();

        $service = app(WorkspaceConversationService::class);
        $started = $service->startConversation($user->id);
        $conversation = AiConversation::find($started['conversation_id']);

        $service->handleMessage($conversation, 'Find a match', []);
        $conversation->refresh();

        // No dating profile set up — enterMatchesWorkspace() bounces back to
        // idle with the "complete your profile" message. That bounce only
        // happens if assignWorkspace() correctly routed into the Matches
        // branch, which is what this test is verifying still works.
        $this->assertSame('idle', $conversation->status);
        $this->assertNull($conversation->workspace_id);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('complete your dating preference', $lastMessage->message);
    }
}
```

- [ ] **Step 2: Run tests to verify they pass**

Run: `php artisan test --filter=WorkspaceRoutingRegressionTest`
Expected: PASS (2 tests, 0 failures) — no production code in this task, so this is a straight pass confirming Tasks 1-3 didn't regress Matches/Marketplace routing.

- [ ] **Step 3: Run the full test suite one final time**

Run: `php artisan test`
Expected: PASS — full suite green, including `WorkspaceEntryFlowTest`, `SocialPostCollectionTest`, `AiConversationSchemaTest`, and `WorkspaceRoutingRegressionTest`.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/AiConversation/WorkspaceRoutingRegressionTest.php
git commit -m "test: add regression coverage for Matches/Marketplace pill routing"
```
