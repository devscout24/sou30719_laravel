# Social Post AI Conversation Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Social Post workspace's rigid two-field ("do you have a description AND an image") slot-filling with a two-phase, AI-driven conversation that asks about the topic first, then details/image, narrating what it understood before showing the preview card.

**Architecture:** Reuse `AiConversation::topic` (already nullable) as the phase discriminator instead of adding a new status enum value — `topic IS NULL` means Phase 1 (topic discovery), `topic IS NOT NULL` means Phase 2 (details/image collection). A new `SocialPostCollectorService` (same constructor-injected-`OpenAIService`, strict-JSON-system-prompt pattern as the existing `WorkspaceIntentClassifierService`/`ReplyIntentClassifierService`) supplies the three AI-generated pieces of text: topic classification, the details-ask, and a pre-preview-card acknowledgment. `WorkspaceConversationService::handleCollecting()`'s non-marketplace branch is rewritten to drive this; `handleMarketplaceCollecting()` is untouched.

**Tech Stack:** Laravel 11, PHP 8.4, `OpenAIService` (OpenAI Chat Completions API, `gpt-4o-mini` by default, vision-capable), MySQL via Eloquent migrations.

## Global Constraints

- Social Post workspace only (`Workspace::SLUG_SOCIAL_POST`) — Event/Interest Hub/Personal Courier are `is_supported = false` and never reach this code path; Market Place has its own separate branch, untouched.
- Image stays a hard requirement to finish a post (existing product rule, not changing). Text description becomes optional once an image is present.
- 3-strike cap: after 3 consecutive unclear Phase-1 replies, stop asking varied AI questions and pivot to a fixed "share a photo" message.
- This repo has no PHPUnit/Pest coverage for API controllers or AI services (`tests/Feature` is Jetstream/Breeze scaffolding only — verified via `find tests -type f`). Established convention for this kind of feature (see `docs/superpowers/plans/2026-07-18-subscription-payments.md`) is a real, live smoke test against the local Herd site (`https://sou30719.test`) using a minted JWT — no mocking infrastructure exists for `OpenAIService`, and a real `OPENAI_API_KEY` is already configured locally. Follow that same pattern here.
- Never commit on the user's behalf — this user handles all `git add`/`git commit` manually. Every task's "commit" step is something to report as ready, not to run.

---

### Task 1: Add `topic_clarify_attempts` to `ai_conversations`

**Files:**
- Create: `database/migrations/2026_07_19_150000_add_topic_clarify_attempts_to_ai_conversations_table.php`
- Modify: `app/Models/AiConversation.php:12-38`

**Interfaces:**
- Produces: `AiConversation::$topic_clarify_attempts` (nullable int, default `0`), readable/writable via `update()` like every other column on this model. Later tasks read/write it directly (no new helper methods needed — `handleTopicDiscovery()` in Task 3 does `($conversation->topic_clarify_attempts ?? 0) + 1`).

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Counts consecutive unclear Phase-1 ("what would you like to post about?")
     * replies in the Social Post workspace's conversation flow, so the AI can
     * pivot to asking for a photo instead of repeating the question forever.
     * Reset to null the moment `topic` gets set.
     */
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->unsignedTinyInteger('topic_clarify_attempts')->nullable()->default(0)->after('topic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('topic_clarify_attempts');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run (PowerShell, from repo root): `php artisan migrate`
Expected: output includes `2026_07_19_150000_add_topic_clarify_attempts_to_ai_conversations_table ... DONE`

- [ ] **Step 3: Update the `AiConversation` model**

In `app/Models/AiConversation.php`, add `topic_clarify_attempts` to `$fillable` (after `'topic'`) and to `$casts`:

```php
    protected $fillable = [
        'slug',
        'user_id',
        'workspace_id',
        'post_id',
        'status',
        'topic',
        'topic_clarify_attempts',
        'description',
        'short_description',
        'image_description',
        'tags',
        'images',
        'ad_type',
        'category',
        'product_url',
        'discount_percentage',
        'show_sale_badge',
    ];

    protected $casts = [
        'status'                  => 'string',
        'topic_clarify_attempts'  => 'integer',
        'images'                  => 'array',
        'tags'                    => 'array',
        'ad_type'                 => 'string',
        'discount_percentage'     => 'decimal:2',
        'show_sale_badge'         => 'boolean',
    ];
```

- [ ] **Step 4: Verify the column exists and is nullable-int**

Run: `php artisan tinker --execute="echo App\Models\AiConversation::create(['user_id' => 1, 'status' => 'idle'])->topic_clarify_attempts === 0 ? 'OK default 0' : 'UNEXPECTED';"`
Expected: `OK default 0` (confirms the migration's `default(0)` applied and the model cast reads it back as an int, not a string). Clean up the row this created: `php artisan tinker --execute="App\Models\AiConversation::latest('id')->first()->delete();"`

- [ ] **Step 5: Report ready to commit**

Do not run `git add`/`git commit` — report the migration and model change as ready for the user to commit themselves:
```
Files changed:
- database/migrations/2026_07_19_150000_add_topic_clarify_attempts_to_ai_conversations_table.php (new)
- app/Models/AiConversation.php
```

---

### Task 2: `SocialPostCollectorService`

**Files:**
- Create: `app/Services/AI/SocialPostCollectorService.php`

**Interfaces:**
- Consumes: `App\Services\AI\OpenAIService::chat(array $messages, bool $jsonMode = false): string` (existing, constructor-injected — same as `WorkspaceIntentClassifierService`).
- Produces (used by Task 3):
  - `classifyTopic(string $message, array $history = []): array{topic: ?string, reply: ?string}`
  - `askForDetails(string $topic, array $history = []): string`
  - `acknowledge(string $topic, string $shortDescription): string`

- [ ] **Step 1: Write the service**

Create `app/Services/AI/SocialPostCollectorService.php`:

```php
<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use Illuminate\Support\Facades\Log;

class SocialPostCollectorService
{
    protected const FALLBACK_TOPIC_REPLY = "I didn't quite catch what you'd like to post about — could you tell me in a few words?";
    protected const FALLBACK_DETAILS_REPLY = 'Great! Want to add any details? And please share a photo to go with it.';
    protected const FALLBACK_ACK_REPLY = "Got it! Here's what I put together:";

    public function __construct(protected OpenAIService $openai)
    {
    }

    /**
     * Phase 1: decide whether a message clearly states a postable topic.
     *
     * @param  array<int, array{role: string, content: string}>  $history  prior turns, oldest first
     * @return array{topic: ?string, reply: ?string} reply is only meaningful when topic is null
     */
    public function classifyTopic(string $message, array $history = []): array
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->topicSystemPrompt()]],
            $history,
            [['role' => 'user', 'content' => $message]],
        );

        try {
            $content = $this->openai->chat($messages, jsonMode: true);
        } catch (AIServiceException $e) {
            Log::warning('Social post topic classification failed', ['error' => $e->getMessage()]);

            return ['topic' => null, 'reply' => self::FALLBACK_TOPIC_REPLY];
        }

        $decoded = json_decode($content, true);
        $topic   = trim((string) ($decoded['topic'] ?? ''));
        $reply   = trim((string) ($decoded['reply'] ?? ''));

        return [
            'topic' => $topic !== '' ? $topic : null,
            'reply' => $reply !== '' ? $reply : self::FALLBACK_TOPIC_REPLY,
        ];
    }

    /**
     * Phase 2 opener: ask for optional elaboration and a required photo, in the
     * AI's own words, referencing the topic that was just established.
     *
     * @param  array<int, array{role: string, content: string}>  $history  prior turns, oldest first
     */
    public function askForDetails(string $topic, array $history = []): string
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->detailsSystemPrompt($topic)]],
            $history,
        );

        try {
            $content = $this->openai->chat($messages);
        } catch (AIServiceException $e) {
            Log::warning('Social post details prompt failed', ['error' => $e->getMessage()]);

            return self::FALLBACK_DETAILS_REPLY;
        }

        $reply = trim($content);

        return $reply !== '' ? $reply : self::FALLBACK_DETAILS_REPLY;
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

    protected function topicSystemPrompt(): string
    {
        return <<<'TEXT'
            You are a friendly assistant inside the "Social" workspace of a social app, helping a user figure out
            what they'd like to post about. Chat naturally — like a real conversation, not a form.

            Decide whether the user's latest message clearly states a topic or subject for a social media post
            (e.g. "food", "my trip to Bali", "my new puppy"). Vague replies ("hey", "idk", "post", "something")
            do NOT count as a clear topic.

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {"topic": "<short 1-4 word topic, or empty string if unclear>", "reply": "<natural 1-2 sentence reply, only used if topic is unclear>"}

            Rules:
            - If a clear topic is present, set "topic" to a short label and "reply" can be empty.
            - If unclear, set "topic" to an empty string and "reply" to a warm, natural follow-up question asking
              what they'd like to post about — vary your wording, don't repeat a question you've already asked in
              this conversation.
            TEXT;
    }

    protected function detailsSystemPrompt(string $topic): string
    {
        return <<<TEXT
            You are a friendly assistant inside the "Social" workspace of a social app. The user just told you
            they want to post about: "{$topic}".

            Reply with ONE short, warm, natural message (1-2 sentences, no markdown) that does two things:
            1. Acknowledges the topic in your own words.
            2. Asks if they'd like to add any details or a description, and asks them to share a photo for the post.

            Do not use a rigid template — vary your phrasing naturally, like a real person chatting.
            TEXT;
    }
}
```

- [ ] **Step 2: Lint it**

Run: `php -l app/Services/AI/SocialPostCollectorService.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Live-verify all three methods against the real OpenAI API**

Create a scratch file (use your own scratch/temp directory, not the repo) `verify_social_collector.php`:

```php
<?php

require 'C:/Users/Max/Desktop/Projects/s/sou30719/vendor/autoload.php';
$app = require 'C:/Users/Max/Desktop/Projects/s/sou30719/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = $app->make(App\Services\AI\SocialPostCollectorService::class);

$clear = $service->classifyTopic('I want to post about food');
echo 'clear topic: ' . json_encode($clear) . "\n";

$vague = $service->classifyTopic('hey');
echo 'vague topic: ' . json_encode($vague) . "\n";

$details = $service->askForDetails('food');
echo 'details ask: ' . $details . "\n";

$ack = $service->acknowledge('food', 'A tasty-looking bowl of ramen with fresh herbs.');
echo 'acknowledge: ' . $ack . "\n";
```

- [ ] **Step 4: Run it and inspect output**

Run: `php verify_social_collector.php` (from the scratch directory)
Expected:
- `clear topic:` line has `"topic":"food"` (or similar short label) and empty/absent `reply`.
- `vague topic:` line has `"topic":null` and a non-empty, natural-sounding `reply` (not a canned string — read it to confirm it's not literally `FALLBACK_TOPIC_REPLY`, which only fires on an actual API error).
- `details ask:` and `acknowledge:` lines are both non-empty, natural sentences (not empty, not raw JSON).

If any output looks like a canned fallback string, check `storage/logs/laravel.log` for an `AIServiceException` warning (`Social post topic classification failed` / `Social post details prompt failed` / `Social post acknowledgment failed`) and confirm `OPENAI_API_KEY` is valid before proceeding.

- [ ] **Step 5: Report ready to commit**

```
Files changed:
- app/Services/AI/SocialPostCollectorService.php (new)
```

---

### Task 3: Rewrite `WorkspaceConversationService`'s Social Post collection flow

**Files:**
- Modify: `app/Services/WorkspaceConversationService.php:19-56` (constants + constructor), `:210-278` (`handleCollecting`), `:743-750` (`guidanceFor`)

**Interfaces:**
- Consumes: `SocialPostCollectorService::classifyTopic()`, `::askForDetails()`, `::acknowledge()` (Task 2). `AiConversation::$topic_clarify_attempts` (Task 1). Existing `PostCuratorService::curate(string $description, array $imagePaths): array{topic, description, short_description, tags}` (unchanged). Existing `recentHistory(AiConversation $conversation, int $limit = 10): array` (unchanged, already defined lower in this same file).
- Produces: no new public methods — `handleCollecting()`'s public signature is unchanged, only its internal behavior for non-Market-Place workspaces changes. New private helpers `handleTopicDiscovery()`, `handleDetailsCollection()`, `curateFromImage()`, `curateNow()`, `finalizePost()` are internal to this class.

- [ ] **Step 1: Replace the Social Post message constants**

In `app/Services/WorkspaceConversationService.php`, replace this block (currently lines 27-32):

```php
    protected const MSG_SELECT_PROMPT = 'Select one of the optional prompts below';
    protected const MSG_UNDER_DEV = 'This feature is currently under development and will be available soon.';
    protected const MSG_SOCIAL_GUIDANCE = 'Share your thoughts and experiences inside the chat interface and attach image(s) to proceed.';
    protected const MSG_NEED_DESCRIPTION = 'Please provide a description for your post.';
    protected const MSG_NEED_IMAGES = 'Please upload at least one image.';
    protected const MSG_NEED_BOTH = 'Please provide a description and upload at least one image.';
```

with:

```php
    protected const MSG_SELECT_PROMPT = 'Select one of the optional prompts below';
    protected const MSG_UNDER_DEV = 'This feature is currently under development and will be available soon.';
    protected const MSG_SOCIAL_OPENING = 'Hi! What would you like to post about today?';
    protected const MSG_TOPIC_FALLBACK = "No worries — just share a photo of what you'd like to post, and I'll take it from there.";
    protected const MSG_STILL_NEED_IMAGE = "Don't forget to share a photo so I can finish your post!";
```

- [ ] **Step 2: Inject the new service in the constructor**

Replace (currently lines 51-56):

```php
    public function __construct(
        protected PostCuratorService $curator,
        protected WorkspaceIntentClassifierService $classifier,
        protected ReplyIntentClassifierService $replyClassifier,
    ) {
    }
```

with:

```php
    public function __construct(
        protected PostCuratorService $curator,
        protected WorkspaceIntentClassifierService $classifier,
        protected ReplyIntentClassifierService $replyClassifier,
        protected \App\Services\AI\SocialPostCollectorService $socialCollector,
    ) {
    }
```

Also add the import near the top of the file (after the existing `use App\Services\AI\ReplyIntentClassifierService;` line):

```php
use App\Services\AI\SocialPostCollectorService;
```

...and simplify the constructor property type to the short class name now that it's imported:

```php
    public function __construct(
        protected PostCuratorService $curator,
        protected WorkspaceIntentClassifierService $classifier,
        protected ReplyIntentClassifierService $replyClassifier,
        protected SocialPostCollectorService $socialCollector,
    ) {
    }
```

- [ ] **Step 3: Replace `handleCollecting()` and add the new phase handlers**

Replace the entire current `handleCollecting()` method (currently lines 210-278, from `protected function handleCollecting(...)` up to — but not including — `protected function handleMarketplaceCollecting(...)`) with:

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

        $reply = $this->socialCollector->askForDetails($result['topic'], $this->recentHistory($conversation));
        $this->storeReply($conversation, $reply);
    }

    /**
     * Phase 2: topic is known, waiting on a photo (description/elaboration is
     * optional). Any text is stored as elaboration; once an image exists, curate.
     */
    protected function handleDetailsCollection(AiConversation $conversation, ?string $text): void
    {
        if ($conversation->hasImages()) {
            $this->curateNow($conversation);
            return;
        }

        if (filled($text)) {
            $conversation->update(['description' => trim($text)]);
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

(Leave `handleMarketplaceCollecting()`, which immediately follows, untouched.)

- [ ] **Step 4: Update `guidanceFor()`'s Social Post line**

Find (around line 743-750):

```php
    protected function guidanceFor(Workspace $workspace): string
    {
        return match ($workspace->slug) {
            Workspace::SLUG_SOCIAL_POST   => self::MSG_SOCIAL_GUIDANCE,
            Workspace::SLUG_MARKET_PLACE  => self::MSG_MARKETPLACE_GUIDANCE,
            default                       => self::MSG_UNDER_DEV,
        };
    }
```

Replace with:

```php
    protected function guidanceFor(Workspace $workspace): string
    {
        return match ($workspace->slug) {
            Workspace::SLUG_SOCIAL_POST   => self::MSG_SOCIAL_OPENING,
            Workspace::SLUG_MARKET_PLACE  => self::MSG_MARKETPLACE_GUIDANCE,
            default                       => self::MSG_UNDER_DEV,
        };
    }
```

- [ ] **Step 5: Lint the file**

Run: `php -l app/Services/WorkspaceConversationService.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Confirm no other file still references the removed constants**

Run: `grep -rn "MSG_SOCIAL_GUIDANCE\|MSG_NEED_DESCRIPTION\|MSG_NEED_IMAGES\|MSG_NEED_BOTH" app/`
Expected: no output (all four were only used inside the method just replaced).

- [ ] **Step 7: Direct-service smoke test (text-only topic → details → image → preview)**

Create a scratch file `verify_social_flow.php`:

```php
<?php

require 'C:/Users/Max/Desktop/Projects/s/sou30719/vendor/autoload.php';
$app = require 'C:/Users/Max/Desktop/Projects/s/sou30719/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'user@user.com')->first();
$workspace = App\Models\Workspace::where('slug', App\Models\Workspace::SLUG_SOCIAL_POST)->first();
$service = $app->make(App\Services\WorkspaceConversationService::class);

$start = $service->startConversation($user->id, $workspace->id);
$conversation = App\Models\AiConversation::find($start['conversation_id']);
echo "opening: " . $conversation->messages()->latest('id')->first()->message . "\n";

// Vague reply — should ask again, not advance.
$service->handleMessage($conversation->fresh(), 'hey', []);
$conversation->refresh();
echo "after vague: topic=" . var_export($conversation->topic, true) . " attempts=" . $conversation->topic_clarify_attempts . "\n";
echo "reply: " . $conversation->messages()->latest('id')->first()->message . "\n";

// Clear topic — should set topic and ask for details.
$service->handleMessage($conversation->fresh(), 'I want to post about food', []);
$conversation->refresh();
echo "after topic: topic=" . var_export($conversation->topic, true) . " attempts=" . var_export($conversation->topic_clarify_attempts, true) . "\n";
echo "reply: " . $conversation->messages()->latest('id')->first()->message . "\n";

// Image-only reply — should curate and reach preview.
$sampleImage = 'posts/4d860f27-8bec-4989-aa47-7a7de9b750d5.png'; // existing seeded file on the public disk
$service->handleMessage($conversation->fresh(), null, [$sampleImage]);
$conversation->refresh();
echo "final status: " . $conversation->status . "\n";
echo "final topic: " . $conversation->topic . "\n";
$last3 = $conversation->messages()->latest('id')->take(3)->get()->reverse()->pluck('type');
echo "last 3 message types: " . $last3->implode(', ') . "\n";
```

- [ ] **Step 8: Run it and check the flow**

Run: `php verify_social_flow.php` (from the scratch directory)
Expected:
- `opening:` is `Hi! What would you like to post about today?`
- After the vague reply: `topic=NULL`, `attempts=1`, and the reply is a natural question (not literally the fallback string, unless the API genuinely errored — check `storage/logs/laravel.log` if so).
- After "I want to post about food": `topic='food'` (or similar short label), `attempts=NULL`, and the reply asks for details/a photo in natural language.
- After the image-only reply: `final status: preview`, `final topic:` is a polished topic (may differ slightly from the seed image's actual content since it's a real seeded post image, not literally food — that's fine, this step is testing the *flow*, not asserting a specific topic string), and `last 3 message types` ends with `message, post, pills` (the acknowledgment, then the preview card, then the pills) — confirms `finalizePost()` stored all three in order.

If `$user` or `$workspace` come back null, run `php artisan db:seed --class=UserSeeder` and confirm `WorkspaceSeeder` has run (`php artisan db:seed --class=WorkspaceSeeder`) first.

- [ ] **Step 9: Report ready to commit**

```
Files changed:
- app/Services/WorkspaceConversationService.php
```

---

### Task 4: End-to-end live HTTP smoke test (the client's exact example flow)

**Files:** none (verification only — exercises Tasks 1-3 through the real API).

**Interfaces:**
- Consumes: `POST /api/conversations`, `GET /api/conversations/{slug}`, `POST /api/conversations/{slug}/messages` (existing routes, unchanged).

- [ ] **Step 1: Mint a JWT and start a Social Post conversation**

Create a scratch file `verify_social_http.php`:

```php
<?php

require 'C:/Users/Max/Desktop/Projects/s/sou30719/vendor/autoload.php';
$app = require 'C:/Users/Max/Desktop/Projects/s/sou30719/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'user@user.com')->first();
$token = Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
$workspaceId = App\Models\Workspace::where('slug', App\Models\Workspace::SLUG_SOCIAL_POST)->value('id');

file_put_contents(__DIR__ . '/token.txt', $token);
echo "user_id={$user->id} workspace_id={$workspaceId} token_written\n";
```

Run: `php verify_social_http.php` (from the scratch directory)
Expected: `user_id=<N> workspace_id=<M> token_written` with no errors.

- [ ] **Step 2: Start the conversation**

Run (replace `<TOKEN>` with `token.txt`'s contents, `<WORKSPACE_ID>` from Step 1):
```bash
curl -sk -X POST https://sou30719.test/api/conversations -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"workspace_id": <WORKSPACE_ID>}'
```
Expected: `"status":true`, `data.slug` present — save it as `<SLUG>`.

- [ ] **Step 3: Fetch details, confirm the opening line**

Run: `curl -sk https://sou30719.test/api/conversations/<SLUG> -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json"`
Expected: `data.messages` has one AI message, `content` = `"Hi! What would you like to post about today?"`, `type: "message"`.

- [ ] **Step 4: Send 3 vague replies in a row, confirm the pivot**

Run this three times in sequence:
```bash
curl -sk -X POST https://sou30719.test/api/conversations/<SLUG>/messages -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -F "message=hey"
```
Then: `curl -sk https://sou30719.test/api/conversations/<SLUG> -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json"`
Expected: the 3rd AI reply (after the 3rd "hey") is exactly `"No worries — just share a photo of what you'd like to post, and I'll take it from there."` — confirms the 3-strike cap fires over real HTTP, not just in the direct-service test.

- [ ] **Step 5: Send a clear topic, confirm the details-ask**

Run:
```bash
curl -sk -X POST https://sou30719.test/api/conversations/<SLUG>/messages -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -F "message=I want to post about food"
```
Then re-fetch details as in Step 3.
Expected: the latest AI message is a natural sentence referencing food and asking for details/a photo (not a literal repeat of the earlier canned fallback).

- [ ] **Step 6: Send an image-only reply, confirm the preview card and acknowledgment**

Run (uses the existing seeded sample image on disk):
```bash
curl -sk -X POST https://sou30719.test/api/conversations/<SLUG>/messages -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -F "images[]=@C:/Users/Max/Desktop/Projects/s/sou30719/storage/app/public/posts/4d860f27-8bec-4989-aa47-7a7de9b750d5.png"
```
Then re-fetch details.
Expected: `data.status` = `"preview"`, and the last 3 messages (in order) are `type: "message"` (the acknowledgment — a natural sentence, not empty), `type: "post"` (the preview card, with `content.images` containing the uploaded file's resolved URL), `type: "pills"` (`["Approve posting to the feed", "Edit post", "Delete post"]`).

- [ ] **Step 7: Confirm no regression on Market Place**

Run:
```bash
curl -sk -X POST https://sou30719.test/api/conversations -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -H "Content-Type: application/json" -d "{\"workspace_id\": $(php -r "require 'C:/Users/Max/Desktop/Projects/s/sou30719/vendor/autoload.php'; \$app = require 'C:/Users/Max/Desktop/Projects/s/sou30719/bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo App\Models\Workspace::where('slug', App\Models\Workspace::SLUG_MARKET_PLACE)->value('id');")}"
```
Then fetch details for the returned slug.
Expected: the opening AI message is still the unchanged `MSG_MARKETPLACE_GUIDANCE` text ("Let's create your advertisement...") — confirms `handleMarketplaceCollecting()` and its guidance line were untouched by this change.

- [ ] **Step 8: Report results**

Summarize pass/fail for Steps 3-7 to the user. This task has no code changes to commit — it's pure verification of Tasks 1-3's combined behavior through the real API.
