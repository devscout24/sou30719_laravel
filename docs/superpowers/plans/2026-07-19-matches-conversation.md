# Matches AI Conversation Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop asking "male or female?" from scratch every time — use the gender/criteria already stated in the message that got the user into the Matches workspace, or their saved `DatingPreference`, and actually filter/rank candidates by any stated criteria (e.g. height) instead of gender alone.

**Architecture:** Two new nullable `ai_conversations` columns (`match_gender`, `match_criteria`) carry state extracted eagerly by `WorkspaceIntentClassifierService::interpret()` the moment it decides Matches fits — before the user even confirms "yes". A new `MatchCriteriaService` judges whether stated criteria is concrete enough to act on, and ranks a bounded, already-gender-filtered candidate list against it in one AI call (not per-candidate). `WorkspaceConversationService`'s Matches section is rewritten to resolve gender from (in order) the extracted value, then the saved preference, then — only if both are unavailable — today's pill-based ask as a fallback.

**Tech Stack:** Laravel 11, PHP 8.4, `OpenAIService` (OpenAI Chat Completions API, `gpt-4o-mini`).

## Global Constraints

- Matches workspace only (`Workspace::SLUG_MATCHES`) — Social Post, Market Place, and the shared `handleIdle()`/`handleConfirmingWorkspace()` mechanics must only gain the minimal additions needed to carry gender/criteria through; their existing behavior for every other workspace is unchanged.
- No new typed columns for height/age/etc. — everything beyond gender stays free text, AI-interpreted. `height` on `dating_profiles` has no enforced format (plain nullable string, no seeded examples, no validation) — do not assume a specific unit or format.
- Criteria matching is a "nice ranking boost," not a hard gate: any AI failure or an unresolvable "still vague after one follow-up" criteria must degrade to searching by gender alone, never block the user from getting suggestions.
- `findMatchCandidates()` must support `$gender === 'both'` (skip the `dating_gender` filter) — previously it only ever received `'male'`/`'female'` from the pill-based classifier, but `DatingPreference->interested_in` allows `'both'` and the new extraction path can produce it too.
- This repo has no PHPUnit/Pest coverage for services or API controllers, and no mocking infrastructure for the OpenAI client. Verification is live: real DB, real OpenAI API (`gpt-4o-mini`, key already configured), against the local Herd site (`https://sou30719.test`) for HTTP-level checks. No seeded dating-profile test data exists anywhere in this repo — verification scripts must create their own throwaway test users/profiles via `firstOrCreate`/`updateOrCreate` (same pattern as `docs/superpowers/plans/2026-07-18-subscription-payments.md`'s `SubscriptionPlan::firstOrCreate(...)` — scratch data for testing, not a permanent seeder).
- Never commit on the user's behalf beyond the confirmed per-task local-commit exception for this Subagent-Driven Development workflow (never pushed). Never push.

---

### Task 1: `match_gender`/`match_criteria` columns + `awaiting_match_criteria` status

**Files:**
- Create: `database/migrations/2026_07_19_160000_add_match_fields_to_ai_conversations_table.php`
- Modify: `app/Models/AiConversation.php:12-38` (fillable/casts), add one helper method near the existing `isAwaiting*()` helpers (around line 107-110)

**Interfaces:**
- Produces: `AiConversation::$match_gender` (nullable string), `AiConversation::$match_criteria` (nullable string), `AiConversation::isAwaitingMatchCriteria(): bool`, and the `'awaiting_match_criteria'` status value. Later tasks read/write these via `update()`/property access like every other column on this model.

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
     * match_gender/match_criteria carry whatever the Matches workspace's
     * conversation-intent classifier already extracted from the user's message
     * (e.g. "I'm looking for a good height woman" -> gender=female,
     * criteria="good height"), so the flow doesn't have to ask "male or
     * female?" when it's already known. awaiting_match_criteria is a new
     * status for the one-round "could you be more specific?" follow-up when
     * criteria was stated but too vague to act on.
     */
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->string('match_gender')->nullable()->after('topic_clarify_attempts');
            $table->text('match_criteria')->nullable()->after('match_gender');
        });

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
    }

    /**
     * Reverse the migrations.
     */
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
                'completed',
                'published',
            ])->default('idle')->change();
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn(['match_gender', 'match_criteria']);
        });
    }
};
```

This follows the exact same enum-`change()` pattern already used successfully in `database/migrations/2026_07_18_140200_add_matches_statuses_to_ai_conversations_table.php` (no new dependency needed — that migration already proves `->change()` on this enum works in this project).

- [ ] **Step 2: Run the migration**

Run (PowerShell): `php artisan migrate`
Expected: output includes `2026_07_19_160000_add_match_fields_to_ai_conversations_table ... DONE`

- [ ] **Step 3: Update the `AiConversation` model**

In `app/Models/AiConversation.php`, the current `$fillable` array (lines 12-29) is:
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
```
Add `'match_gender'` and `'match_criteria'` after `'topic_clarify_attempts'`:
```php
    protected $fillable = [
        'slug',
        'user_id',
        'workspace_id',
        'post_id',
        'status',
        'topic',
        'topic_clarify_attempts',
        'match_gender',
        'match_criteria',
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
```

`$casts` needs no change — both new columns are plain strings, Eloquent's default is already correct.

Then find the existing `isAwaitingMatchGender()` helper (around line 107-110):
```php
    public function isAwaitingMatchGender(): bool
    {
        return $this->status === 'awaiting_match_gender';
    }
```
Add a matching helper directly after it:
```php
    public function isAwaitingMatchCriteria(): bool
    {
        return $this->status === 'awaiting_match_criteria';
    }
```

- [ ] **Step 4: Verify**

Run: `php artisan tinker --execute="$c = App\Models\AiConversation::create(['user_id' => 1, 'status' => 'idle', 'match_gender' => 'female', 'match_criteria' => 'good height']); echo $c->match_gender . ' / ' . $c->match_criteria . ' / ' . ($c->isAwaitingMatchCriteria() ? 'true' : 'false');"`
Expected: `female / good height / false` (the third part confirms the helper works — status is `idle` here, not `awaiting_match_criteria`).
Clean up: `php artisan tinker --execute="App\Models\AiConversation::latest('id')->first()->delete();"`

- [ ] **Step 5: Report ready to commit**

```
Files changed:
- database/migrations/2026_07_19_160000_add_match_fields_to_ai_conversations_table.php (new)
- app/Models/AiConversation.php
```

---

### Task 2: `MatchCriteriaService`

**Files:**
- Create: `app/Services/AI/MatchCriteriaService.php`

**Interfaces:**
- Consumes: `App\Services\AI\OpenAIService::chat(array $messages, bool $jsonMode = false): string` (existing).
- Produces (used by Task 4):
  - `isConcrete(string $criteria): bool`
  - `rankCandidates(string $criteria, \Illuminate\Support\Collection $candidates): array<int, array{user_id: int, score: int, reason: string}>` — `$candidates` is a collection of `App\Models\User` with `datingProfile` eager-loaded (matches what `findMatchCandidates()` already returns).

- [ ] **Step 1: Write the service**

Create `app/Services/AI/MatchCriteriaService.php`:

```php
<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MatchCriteriaService
{
    public function __construct(protected OpenAIService $openai)
    {
    }

    /**
     * Is this free-text preference specific enough to search with, or too
     * vague to act on (e.g. "good height" vs. "5'6\" or taller")?
     * Degrades to true (treat as concrete, proceed) on AI failure — a
     * transient outage must not block a user from getting matches.
     */
    public function isConcrete(string $criteria): bool
    {
        $messages = [
            ['role' => 'system', 'content' => $this->concreteSystemPrompt()],
            ['role' => 'user', 'content' => $criteria],
        ];

        try {
            $content = $this->openai->chat($messages, jsonMode: true);
        } catch (AIServiceException $e) {
            Log::warning('Match criteria concreteness check failed', ['error' => $e->getMessage()]);

            return true;
        }

        $decoded = json_decode($content, true);

        return (bool) ($decoded['concrete'] ?? true);
    }

    /**
     * Rank a bounded list of candidates against free-text criteria in one
     * call (not one call per candidate). Degrades to an empty array on AI
     * failure — callers should fall back to an unranked candidate list.
     *
     * @param  Collection<int, \App\Models\User>  $candidates  each with datingProfile loaded
     * @return array<int, array{user_id: int, score: int, reason: string}>
     */
    public function rankCandidates(string $criteria, Collection $candidates): array
    {
        $profiles = $candidates->map(function ($candidate) {
            $profile = $candidate->datingProfile;

            return [
                'user_id' => $candidate->id,
                'height'  => $profile?->height,
                'about'   => $profile?->about ?? $profile?->about_me,
                'hobbies' => $profile?->hobbies,
            ];
        })->values()->all();

        $messages = [
            ['role' => 'system', 'content' => $this->rankSystemPrompt()],
            ['role' => 'user', 'content' => json_encode(['criteria' => $criteria, 'candidates' => $profiles])],
        ];

        try {
            $content = $this->openai->chat($messages, jsonMode: true);
        } catch (AIServiceException $e) {
            Log::warning('Match candidate ranking failed', ['error' => $e->getMessage()]);

            return [];
        }

        $decoded  = json_decode($content, true);
        $rankings = (array) ($decoded['rankings'] ?? []);

        return array_values(array_filter(array_map(function ($ranking) {
            if (!is_array($ranking) || !isset($ranking['user_id'])) {
                return null;
            }

            return [
                'user_id' => (int) $ranking['user_id'],
                'score'   => max(0, min(100, (int) ($ranking['score'] ?? 0))),
                'reason'  => trim((string) ($ranking['reason'] ?? '')),
            ];
        }, $rankings)));
    }

    protected function concreteSystemPrompt(): string
    {
        return <<<'TEXT'
            You help a dating app determine whether a stated match preference is specific enough to search with.

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {"concrete": true|false}

            Rules:
            - true: the preference names a specific, actionable value (e.g. "5'6\" or taller", "at least 170cm", "loves hiking and camping").
            - false: the preference is vague with no actionable value (e.g. "good height", "nice", "attractive", "tall").
            TEXT;
    }

    protected function rankSystemPrompt(): string
    {
        return <<<'TEXT'
            You help a dating app rank candidate profiles against a user's stated preference.

            You are given a JSON object with "criteria" (the user's stated preference, free text) and
            "candidates" (an array of {user_id, height, about, hobbies} — any field may be null if unset).

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {"rankings": [{"user_id": <int>, "score": <0-100 int>, "reason": "<short reason, 1 sentence>"}]}

            Rules:
            - Include every candidate you were given, even a poor fit (score them low, don't omit them).
            - "score" reflects how well the candidate's available fields match the stated criteria.
            - "reason" is a short, natural explanation of the score (e.g. "Height listed as 5'7\", matches your preference").
            - If a candidate's relevant fields are null/unset, score conservatively around 50 (unknown, not a mismatch) and say so in "reason".
            TEXT;
    }
}
```

- [ ] **Step 2: Lint it**

Run: `php -l app/Services/AI/MatchCriteriaService.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Live-verify both methods**

Create a scratch file `verify_match_criteria.php`:

```php
<?php

require 'C:/Users/Max/Desktop/Projects/s/sou30719/vendor/autoload.php';
$app = require 'C:/Users/Max/Desktop/Projects/s/sou30719/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = $app->make(App\Services\AI\MatchCriteriaService::class);

echo 'concrete ("5ft6 or taller"): ' . ($service->isConcrete("5ft6 or taller") ? 'true' : 'false') . "\n";
echo 'concrete ("good height"): ' . ($service->isConcrete('good height') ? 'true' : 'false') . "\n";

// Throwaway candidates for the ranking call — not persisted, just Eloquent
// models in memory with a datingProfile relation manually attached.
$profileTall = new App\Models\DatingProfile(['height' => "5'8\"", 'about' => 'Love hiking and the outdoors.']);
$profileShort = new App\Models\DatingProfile(['height' => "5'2\"", 'about' => 'Enjoy reading and quiet nights in.']);

$candidateA = new App\Models\User(['id' => 9001, 'name' => 'Tall Candidate']);
$candidateA->setRelation('datingProfile', $profileTall);
$candidateB = new App\Models\User(['id' => 9002, 'name' => 'Short Candidate']);
$candidateB->setRelation('datingProfile', $profileShort);

$rankings = $service->rankCandidates("looking for someone at least 5'6\"", collect([$candidateA, $candidateB]));
echo 'rankings: ' . json_encode($rankings) . "\n";
```

- [ ] **Step 4: Run it and inspect output**

Run: `php verify_match_criteria.php` (from the scratch directory)
Expected:
- `concrete ("5ft6 or taller"): true`
- `concrete ("good height"): false`
- `rankings:` an array of two entries (`user_id: 9001` and `9002`), each with an int `score` 0-100 and a non-empty `reason`. The taller candidate (9001, `5'8"`) should score noticeably higher than the shorter one (9002, `5'2"`) given the stated "at least 5'6\"" criteria — if the ordering looks reversed, investigate before proceeding.

- [ ] **Step 5: Report ready to commit**

```
Files changed:
- app/Services/AI/MatchCriteriaService.php (new)
```

---

### Task 3: Extend `WorkspaceIntentClassifierService` to extract match gender/criteria

**Files:**
- Modify: `app/Services/AI/WorkspaceIntentClassifierService.php` (full file — it's 75 lines, small enough to replace in one step)

**Interfaces:**
- Produces: `interpret()`'s return shape grows from `array{workspace: ?Workspace, reply: string}` to `array{workspace: ?Workspace, reply: string, match_gender: ?string, match_criteria: ?string}`. `match_gender` is one of `'male'|'female'|'both'|null`; both new fields are `null` whenever the resolved workspace isn't Matches (`Workspace::SLUG_MATCHES`) or no workspace was confidently resolved at all. Task 4's `handleIdle()` reads these two new keys.

- [ ] **Step 1: Replace the file**

Replace the full contents of `app/Services/AI/WorkspaceIntentClassifierService.php` with:

```php
<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WorkspaceIntentClassifierService
{
    protected const CONFIDENCE_THRESHOLD = 0.6;
    protected const FALLBACK_REPLY = "I couldn't quite tell what you're looking to do. Could you be more specific, or choose one of the options below?";

    public function __construct(protected OpenAIService $openai)
    {
    }

    /**
     * Have a natural back-and-forth with the user while working out which workspace
     * (if any) matches their intent. Always returns a conversational reply, even
     * when no workspace is confidently identified yet.
     *
     * When the resolved workspace is Matches, also extracts any stated gender
     * preference and any other stated preference as free text (e.g. "I'm looking
     * for a good height woman" -> gender "female", criteria "good height"), so the
     * Matches flow doesn't have to ask "male or female?" when it's already known.
     * Both are null whenever the resolved workspace isn't Matches.
     *
     * @param  Collection<int, Workspace>  $workspaces
     * @param  array<int, array{role: string, content: string}>  $history  prior turns, oldest first
     * @return array{workspace: ?Workspace, reply: string, match_gender: ?string, match_criteria: ?string}
     */
    public function interpret(string $text, Collection $workspaces, array $history = []): array
    {
        $options = $workspaces->map(fn (Workspace $workspace) => [
            'id'          => $workspace->id,
            'title'       => $workspace->title,
            'description' => $workspace->description,
            'prompt'      => $workspace->prompt,
        ])->values()->all();

        $matchesWorkspaceId = $workspaces->firstWhere('slug', Workspace::SLUG_MATCHES)?->id;

        $system = 'You are a friendly assistant chatting with a user inside a social app, helping them figure out '
            . "what they'd like to do. Reply naturally to whatever they say — greetings, small talk, or a stated "
            . "goal — like a real conversation, not a form. The available workspaces (things you can help them "
            . 'do) are listed below as JSON; each needs a description and at least one image once you know which '
            . 'one fits. If nothing said so far points to a specific workspace, keep the conversation going: ask '
            . "what they're looking to do or planning, in your own words, without repeating yourself if you've "
            . "already asked. Never invent a workspace_id that isn't in the list.\n\n"
            . "If the workspace that fits is the Matches workspace (id {$matchesWorkspaceId}), also extract any "
            . "stated gender preference and any other stated preference as free text — e.g. \"I'm looking for a "
            . "good height woman\" gives gender \"female\", criteria \"good height\"; \"match with him or her\" "
            . "gives gender \"both\", criteria null. Use null for either field when not stated, and leave both "
            . "null whenever the fitting workspace isn't Matches.\n\n"
            . 'Respond with strict JSON only, no prose outside the JSON: '
            . '{"workspace_id": <int or null>, "confidence": <float 0-1>, "reply": "<your natural reply, 1-3 sentences>", '
            . '"match_gender": <"male"|"female"|"both"|null>, "match_criteria": <string or null>}. '
            . "Use workspace_id null and confidence 0 if you're not yet confident which workspace fits.\n\n"
            . 'Workspaces: ' . json_encode($options);

        $messages = array_merge(
            [['role' => 'system', 'content' => $system]],
            $history,
            [['role' => 'user', 'content' => $text]],
        );

        try {
            $content = $this->openai->chat($messages, jsonMode: true);
        } catch (AIServiceException $e) {
            Log::warning('Workspace intent interpretation failed', ['error' => $e->getMessage()]);

            return ['workspace' => null, 'reply' => self::FALLBACK_REPLY, 'match_gender' => null, 'match_criteria' => null];
        }

        $decoded = json_decode($content, true);
        $workspaceId = $decoded['workspace_id'] ?? null;
        $confidence  = (float) ($decoded['confidence'] ?? 0);
        $reply       = trim((string) ($decoded['reply'] ?? '')) ?: self::FALLBACK_REPLY;

        $workspace = ($workspaceId && $confidence >= self::CONFIDENCE_THRESHOLD)
            ? $workspaces->firstWhere('id', $workspaceId)
            : null;

        $matchGender = null;
        $matchCriteria = null;

        if ($workspace && $workspace->slug === Workspace::SLUG_MATCHES) {
            $rawGender   = $decoded['match_gender'] ?? null;
            $matchGender = in_array($rawGender, ['male', 'female', 'both'], true) ? $rawGender : null;

            $rawCriteria   = $decoded['match_criteria'] ?? null;
            $matchCriteria = filled($rawCriteria) ? trim((string) $rawCriteria) : null;
        }

        return ['workspace' => $workspace, 'reply' => $reply, 'match_gender' => $matchGender, 'match_criteria' => $matchCriteria];
    }
}
```

- [ ] **Step 2: Lint it**

Run: `php -l app/Services/AI/WorkspaceIntentClassifierService.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Confirm the only other caller of `interpret()` still works**

Run: `grep -rn "classifier->interpret\|WorkspaceIntentClassifierService" app/ --include=*.php`
Expected: the only call site is `WorkspaceConversationService::handleIdle()` (`app/Services/WorkspaceConversationService.php`, around line 144) — Task 4 updates that call site to read the two new return keys. If this grep shows any other caller, stop and report NEEDS_CONTEXT — this task assumed a single call site.

- [ ] **Step 4: Live-verify the extraction for all three documented cases**

Create a scratch file `verify_workspace_intent.php`:

```php
<?php

require 'C:/Users/Max/Desktop/Projects/s/sou30719/vendor/autoload.php';
$app = require 'C:/Users/Max/Desktop/Projects/s/sou30719/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = $app->make(App\Services\AI\WorkspaceIntentClassifierService::class);
$workspaces = App\Models\Workspace::active()->get();

$r1 = $service->interpret("I'm looking for a good height woman", $workspaces);
echo 'specific: workspace=' . ($r1['workspace']?->slug ?? 'null') . ' gender=' . var_export($r1['match_gender'], true) . ' criteria=' . var_export($r1['match_criteria'], true) . "\n";

$r2 = $service->interpret('match with him or her', $workspaces);
echo 'vague-both: workspace=' . ($r2['workspace']?->slug ?? 'null') . ' gender=' . var_export($r2['match_gender'], true) . ' criteria=' . var_export($r2['match_criteria'], true) . "\n";

$r3 = $service->interpret('I want to sell my old bicycle', $workspaces);
echo 'non-matches: workspace=' . ($r3['workspace']?->slug ?? 'null') . ' gender=' . var_export($r3['match_gender'], true) . ' criteria=' . var_export($r3['match_criteria'], true) . "\n";
```

- [ ] **Step 5: Run it and inspect output**

Run: `php verify_workspace_intent.php` (from the scratch directory)
Expected:
- `specific:` — `workspace=matches`, `gender='female'`, `criteria=` a non-null string mentioning height (e.g. `'good height'`).
- `vague-both:` — `workspace=matches`, `gender='both'`, `criteria=NULL`.
- `non-matches:` — `workspace=market_place` (or another non-Matches workspace), `gender=NULL`, `criteria=NULL` — confirms the fields are correctly suppressed for non-Matches workspaces.

If any result differs, it may be legitimate live-model variance on the exact wording — re-run once before treating it as a bug; a Matches-workspace resolution with the wrong `match_gender` value would be a real problem, but a slightly different (but still correct) `criteria` string is fine.

- [ ] **Step 6: Report ready to commit**

```
Files changed:
- app/Services/AI/WorkspaceIntentClassifierService.php
```

---

### Task 4: Rewrite `WorkspaceConversationService`'s Matches flow

**Files:**
- Modify: `app/Services/WorkspaceConversationService.php` — constructor (lines 51-57), `handleMessage()`'s dispatch (lines 114-122), `handleIdle()` (lines 129-159), `handleConfirmingWorkspace()` (lines 161-189), the entire Matches section (lines 548-612, from `// ─── Matches ───` through the end of `findMatchCandidates()`), and `storeMatchSuggestions()` (lines 700-722)

**Interfaces:**
- Consumes: `MatchCriteriaService::isConcrete()`/`::rankCandidates()` (Task 2), `WorkspaceIntentClassifierService::interpret()`'s extended return shape (Task 3), `AiConversation::$match_gender`/`$match_criteria`/`isAwaitingMatchCriteria()` (Task 1).
- Produces: no new public methods. New private helpers `proceedWithGenderResolved()`, `handleAwaitingMatchCriteria()`, `searchMatches()`.

- [ ] **Step 1: Add the constructor dependency**

Replace (currently lines 51-57):

```php
    public function __construct(
        protected PostCuratorService $curator,
        protected WorkspaceIntentClassifierService $classifier,
        protected ReplyIntentClassifierService $replyClassifier,
        protected SocialPostCollectorService $socialCollector,
    ) {
    }
```

with:

```php
    public function __construct(
        protected PostCuratorService $curator,
        protected WorkspaceIntentClassifierService $classifier,
        protected ReplyIntentClassifierService $replyClassifier,
        protected SocialPostCollectorService $socialCollector,
        protected \App\Services\AI\MatchCriteriaService $matchCriteria,
    ) {
    }
```

Also add the import near the top (after `use App\Services\AI\SocialPostCollectorService;`):

```php
use App\Services\AI\MatchCriteriaService;
```

...then simplify the constructor property type to the short class name:

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

- [ ] **Step 2: Add the new status to the dispatch `match`**

Replace (currently lines 114-122):

```php
        match ($conversation->status) {
            'idle'                      => $this->handleIdle($conversation, $text),
            'confirming_workspace'      => $this->handleConfirmingWorkspace($conversation, $text),
            'awaiting_match_gender'     => $this->handleAwaitingMatchGender($conversation, $text),
            'collecting'                => $this->handleCollecting($conversation, $text, $imagePaths, $extra),
            'preview'                   => $this->handlePreview($conversation, $text),
            'awaiting_edit_instruction' => $this->handleEditInstruction($conversation, $text),
            default                     => $this->storeReply($conversation, self::MSG_CONVERSATION_DONE),
        };
```

with:

```php
        match ($conversation->status) {
            'idle'                      => $this->handleIdle($conversation, $text),
            'confirming_workspace'      => $this->handleConfirmingWorkspace($conversation, $text),
            'awaiting_match_gender'     => $this->handleAwaitingMatchGender($conversation, $text),
            'awaiting_match_criteria'   => $this->handleAwaitingMatchCriteria($conversation, $text),
            'collecting'                => $this->handleCollecting($conversation, $text, $imagePaths, $extra),
            'preview'                   => $this->handlePreview($conversation, $text),
            'awaiting_edit_instruction' => $this->handleEditInstruction($conversation, $text),
            default                     => $this->storeReply($conversation, self::MSG_CONVERSATION_DONE),
        };
```

- [ ] **Step 3: Persist extracted gender/criteria in `handleIdle()`**

Replace (currently lines 129-159):

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

        $conversation->update(['workspace_id' => $result['workspace']->id, 'status' => 'confirming_workspace']);

        $this->storeReply(
            $conversation,
            sprintf('It sounds like you want to: "%s" — is that right?', $result['workspace']->prompt)
        );
        $this->storePills($conversation, $this->confirmationPills());
    }
```

with:

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
```

- [ ] **Step 4: Reset extracted gender/criteria when the workspace guess is declined**

Replace (currently lines 161-189):

```php
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
```

with:

```php
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

- [ ] **Step 5: Rewrite the entire Matches section**

Replace everything from the `// ─── Matches ───` comment (currently line 548) through the end of `findMatchCandidates()` (currently line 612, the closing `}` of that method, right before the `// ─── Message Persistence ───` comment at line 614) with:

```php
    // ─── Matches ─────────────────────────────────────────────────────────────

    protected function enterMatchesWorkspace(AiConversation $conversation, Workspace $workspace): void
    {
        /** @var User $user */
        $user = $conversation->user()->first();

        if (!$user || !$user->hasCompletedDatingProfile()) {
            $conversation->update(['workspace_id' => null, 'status' => 'idle']);
            $this->storeReply($conversation, self::MSG_PROFILE_INCOMPLETE);
            $this->storePills($conversation, $this->activePrompts());
            return;
        }

        $conversation->update(['workspace_id' => $workspace->id]);

        $gender = $conversation->match_gender ?: $user->datingPreference->interested_in;

        if (!$gender) {
            $conversation->update(['status' => 'awaiting_match_gender']);
            $this->storeReply($conversation, self::MSG_ASK_GENDER);
            $this->storePills($conversation, $this->genderPills());
            return;
        }

        if (!$conversation->match_gender) {
            $conversation->update(['match_gender' => $gender]);
        }

        $this->proceedWithGenderResolved($conversation, $gender);
    }

    protected function handleAwaitingMatchGender(AiConversation $conversation, ?string $text): void
    {
        $gender = $this->replyClassifier->classifyGender((string) $text);

        if (!$gender) {
            $this->storeReply($conversation, self::MSG_ASK_GENDER_AGAIN);
            $this->storePills($conversation, $this->genderPills());
            return;
        }

        $conversation->update(['match_gender' => $gender]);

        $this->proceedWithGenderResolved($conversation, $gender);
    }

    /**
     * Gender is resolved (from the message, saved preference, or pills). If
     * criteria was stated but is too vague to act on, ask once for specifics;
     * otherwise (or after that one round) go straight to searching.
     */
    protected function proceedWithGenderResolved(AiConversation $conversation, string $gender): void
    {
        $criteria = $conversation->match_criteria;

        if ($criteria && !$this->matchCriteria->isConcrete($criteria)) {
            $conversation->update(['status' => 'awaiting_match_criteria']);
            $this->storeReply(
                $conversation,
                sprintf('Could you be a bit more specific about "%s"? For example, an exact number or range would help.', $criteria)
            );
            return;
        }

        $this->searchMatches($conversation, $gender, $criteria);
    }

    /**
     * One follow-up round only — matching-by-criteria is a ranking boost, not
     * a hard gate, so proceed regardless of whether the reply is concrete now.
     */
    protected function handleAwaitingMatchCriteria(AiConversation $conversation, ?string $text): void
    {
        $criteria = filled($text) ? trim($text) : $conversation->match_criteria;

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
                    'compatibility_score' => $ranking['score'] ?? null,
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
```

(The `// ─── Message Persistence ───` comment and everything after it, e.g. `storeReply()`, stays exactly where it was — untouched.)

- [ ] **Step 6: Update `storeMatchSuggestions()` to include the ranking**

Replace (currently lines 700-722):

```php
    /**
     * Store suggested dating-profile matches (Matches workspace).
     *
     * @param  \Illuminate\Support\Collection<int, User>  $candidates
     */
    protected function storeMatchSuggestions(AiConversation $conversation, $candidates): AiMessage
    {
        $payload = $candidates->map(function (User $candidate) {
            $profile = $candidate->datingProfile;
            $photo   = $profile?->images?->firstWhere('is_primary', true) ?? $profile?->images?->first();

            return [
                'user_id'  => $candidate->id,
                'name'     => $candidate->name,
                'username' => $candidate->username,
                'city'     => $profile?->dating_location ?? $profile?->city,
                'about'    => $profile?->about ?? $profile?->about_me,
                'photo'    => $photo ? ['path' => $photo->image_path] : null,
            ];
        })->values()->all();

        return AiMessage::create([
            'conversation_id' => $conversation->id,
            'sender'          => 'ai',
            'type'            => 'matches',
            'message'         => json_encode($payload),
        ]);
    }
```

with:

```php
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
                'user_id'             => $candidate->id,
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
```

- [ ] **Step 7: Lint the file**

Run: `php -l app/Services/WorkspaceConversationService.php`
Expected: `No syntax errors detected`

- [ ] **Step 8: Set up throwaway test data and live-verify all three paths**

Create a scratch file `verify_matches_flow.php`:

```php
<?php

require 'C:/Users/Max/Desktop/Projects/s/sou30719/vendor/autoload.php';
$app = require 'C:/Users/Max/Desktop/Projects/s/sou30719/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'user@user.com')->first();

// Give the primary test user a completed dating profile + preference (interested_in: female).
$profile = App\Models\DatingProfile::updateOrCreate(
    ['user_id' => $user->id],
    ['dating_gender' => 'male', 'is_active' => true, 'height' => "5'10\""]
);
App\Models\DatingPreference::updateOrCreate(
    ['user_id' => $user->id],
    ['interested_in' => 'female', 'min_age' => 18, 'max_age' => 50, 'max_distance' => 50]
);

// Two throwaway female candidates: one tall, one short.
$tallUser = App\Models\User::updateOrCreate(
    ['email' => 'verify-tall-candidate@example.com'],
    ['name' => 'Verify Tall Candidate', 'username' => 'verify_tall', 'password' => 'password', 'status' => 'active', 'email_verified_at' => now()]
);
App\Models\DatingProfile::updateOrCreate(
    ['user_id' => $tallUser->id],
    ['dating_gender' => 'female', 'is_active' => true, 'height' => "5'8\"", 'about' => 'Love hiking.']
);

$shortUser = App\Models\User::updateOrCreate(
    ['email' => 'verify-short-candidate@example.com'],
    ['name' => 'Verify Short Candidate', 'username' => 'verify_short', 'password' => 'password', 'status' => 'active', 'email_verified_at' => now()]
);
App\Models\DatingProfile::updateOrCreate(
    ['user_id' => $shortUser->id],
    ['dating_gender' => 'female', 'is_active' => true, 'height' => "5'1\"", 'about' => 'Enjoy quiet nights in.']
);

$workspace = App\Models\Workspace::where('slug', App\Models\Workspace::SLUG_MATCHES)->first();
$service = $app->make(App\Services\WorkspaceConversationService::class);

// Path A: specific criteria stated up front ("I'm looking for a good height woman").
$startA = $service->startConversation($user->id);
$convA = App\Models\AiConversation::find($startA['conversation_id']);
$service->handleMessage($convA->fresh(), "I'm looking for a good height woman", []);
$convA->refresh();
echo "A after intent: status={$convA->status} gender=" . var_export($convA->match_gender, true) . " criteria=" . var_export($convA->match_criteria, true) . "\n";
if ($convA->status === 'confirming_workspace') {
    $service->handleMessage($convA->fresh(), 'yes', []);
    $convA->refresh();
    echo "A after yes: status={$convA->status}\n";
}
if ($convA->status === 'awaiting_match_criteria') {
    $service->handleMessage($convA->fresh(), "at least 5'6\"", []);
    $convA->refresh();
    echo "A after specifics: status={$convA->status}\n";
}
$lastMsgA = $convA->messages()->reorder('created_at')->reorder('id')->latest('id')->first();
echo "A last message type: {$lastMsgA->type}\n";
if ($lastMsgA->type === 'matches') {
    $payload = json_decode($lastMsgA->message, true);
    echo "A matches payload: " . json_encode($payload) . "\n";
}

// Path B: vague intent ("match with him or her") should skip straight to search using the saved preference (female), no gender pills asked.
$startB = $service->startConversation($user->id);
$convB = App\Models\AiConversation::find($startB['conversation_id']);
$service->handleMessage($convB->fresh(), 'match with him or her', []);
$convB->refresh();
echo "B after intent: status={$convB->status} gender=" . var_export($convB->match_gender, true) . "\n";
if ($convB->status === 'confirming_workspace') {
    $service->handleMessage($convB->fresh(), 'yes', []);
    $convB->refresh();
    echo "B after yes: status={$convB->status}\n";
}
$lastMsgB = $convB->messages()->reorder('created_at')->reorder('id')->latest('id')->first();
echo "B last message type: {$lastMsgB->type}\n";

// Path C: bare pill tap (exact workspace prompt text) — no gender/criteria extracted at all, must fall back to saved preference too (not ask pills), since the primary test user already has one.
$startC = $service->startConversation($user->id);
$convC = App\Models\AiConversation::find($startC['conversation_id']);
$service->handleMessage($convC->fresh(), 'Find a match', []);
$convC->refresh();
echo "C after pill: status={$convC->status} gender=" . var_export($convC->match_gender, true) . "\n";
```

- [ ] **Step 9: Run it and check all three paths**

Run: `php verify_matches_flow.php` (from the scratch directory)
Expected:
- **Path A:** `gender='female'`, `criteria=` something mentioning height, right after the intent message (before any "yes"). If the classifier landed in `confirming_workspace`, "yes" moves it to `awaiting_match_criteria` (since "good height" is vague) — sending `"at least 5'6\""` then moves it to `completed`. Final message type is `matches`, and its payload's two candidates both carry non-null `compatibility_score`/`reason`, with the tall candidate (`verify_tall`, `5'8"`) scoring higher than the short one (`verify_short`, `5'1"`) given the "at least 5'6\"" criteria.
- **Path B:** `gender='both'` captured from the message itself (not the saved `'female'` preference, since the message explicitly said "him or her") — confirms message-stated gender takes priority over the saved preference. Final message type is `matches` (no `awaiting_match_gender` detour — gender was resolved from the message).
- **Path C:** the bare pill tap extracts no gender from the message (expected, exact-match bypasses the classifier) but `enterMatchesWorkspace()` should still resolve gender from the saved `DatingPreference` (`'female'`) rather than falling through to `awaiting_match_gender` — confirm `status` is not `awaiting_match_gender` and `gender='female'` here too.

If any path's `status` gets stuck somewhere unexpected, or `gender` doesn't resolve as described, investigate before proceeding — do not silently accept a wrong result.

- [ ] **Step 10: Report ready to commit**

```
Files changed:
- app/Services/WorkspaceConversationService.php
```

Note in your report: the migration's throwaway verify users (`verify-tall-candidate@example.com`, `verify-short-candidate@example.com`) and the primary test user's new dating profile/preference are left in the DB after this task (matches the established convention of scratch data staying around for later tasks/manual poking, same as the subscription-payments plan's `verify-plus` plan) — do not delete them.

---

### Task 5: End-to-end live HTTP smoke test

**Files:** none (verification only — exercises Tasks 1-4 through the real API).

**Interfaces:**
- Consumes: `POST /api/conversations`, `GET /api/conversations/{slug}`, `POST /api/conversations/{slug}/messages` (existing routes, unchanged).

- [ ] **Step 1: Mint a JWT**

Create a scratch file `verify_matches_http.php`:

```php
<?php

require 'C:/Users/Max/Desktop/Projects/s/sou30719/vendor/autoload.php';
$app = require 'C:/Users/Max/Desktop/Projects/s/sou30719/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'user@user.com')->first();
$token = Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);

file_put_contents(__DIR__ . '/token.txt', $token);
echo "user_id={$user->id} token_written\n";
```

Run: `php verify_matches_http.php` (from the scratch directory)
Expected: `user_id=<N> token_written`. (This reuses the same `user@user.com` account Task 4 already gave a completed dating profile + `female` preference and the two throwaway candidate profiles — no need to recreate them.)

- [ ] **Step 2: Start a conversation with a specific, vague-attribute message**

Run (replace `<TOKEN>`):
```bash
curl -sk -X POST https://sou30719.test/api/conversations -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{}'
```
Expected: `"status":true`, `data.slug` present — save as `<SLUG>`.

- [ ] **Step 3: Send the specific-but-vague-attribute message**

Run:
```bash
curl -sk -X POST https://sou30719.test/api/conversations/<SLUG>/messages -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -F "message=I'm looking for a good height woman"
```
Then: `curl -sk https://sou30719.test/api/conversations/<SLUG> -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json"`
Expected: `data.status` is `confirming_workspace`, with a reply along the lines of `"It sounds like you want to: \"Find a match\" — is that right?"` and confirmation pills.

- [ ] **Step 4: Confirm, then check the criteria clarification fires**

Run:
```bash
curl -sk -X POST https://sou30719.test/api/conversations/<SLUG>/messages -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -F "message=yes"
```
Re-fetch details.
Expected: `data.status` is `awaiting_match_criteria` (since "good height" is vague), with a natural clarifying reply mentioning the stated criteria — **not** the old unconditional "What are you looking for — male or female?" (gender was already known from the first message, so that question must not appear).

- [ ] **Step 5: Give specifics, confirm the match results**

Run:
```bash
curl -sk -X POST https://sou30719.test/api/conversations/<SLUG>/messages -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -F "message=at least 5'6\""
```
Re-fetch details.
Expected: `data.status` is `completed`, the last message has `type: "matches"`, and its `content` array includes both throwaway candidates (`verify_tall`/`verify_short` from Task 4, matched by name/username) each with a non-null `compatibility_score` and `reason` — the taller candidate scoring higher.

- [ ] **Step 6: Confirm no regression on the plain gender-only path**

Start a fresh conversation and tap the exact Matches pill (no criteria at all):
```bash
curl -sk -X POST https://sou30719.test/api/conversations -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{}'
```
Then (new `<SLUG2>`):
```bash
curl -sk -X POST https://sou30719.test/api/conversations/<SLUG2>/messages -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -F "message=Find a match"
```
Re-fetch details.
Expected: `data.status` goes straight to `completed` (gender resolved from the saved `female` preference, no criteria to clarify) with a `matches` message whose candidates have `compatibility_score: null` and `reason: null` (no criteria was given, so no ranking call was made — confirms the plain path is unchanged from before this feature).

- [ ] **Step 7: Report results**

Summarize pass/fail for Steps 2-6 to the user. No code changes in this task — pure verification of Tasks 1-4's combined behavior through the real API.
