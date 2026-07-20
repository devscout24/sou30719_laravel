# Matches Search API — Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a traditional REST browse/search API for dating candidates — 6 fixed tabs + up to 5 user custom tabs, DatingPreference-based filters — fully independent of the Feed/Post module.

**Architecture:** Mirrors Feed Search's tab pattern (`UserFeedTopic` → `PostController::feed()`) with an entirely separate table/model/controller stack (`match_topics` → `MatchTopic` → `MatchTopicController` / `MatchSearchController`). Favorites and connection-status reuse the existing Friends module (`SavedProfile`, `UserConnection`, `ConnectionRequest`) unchanged — zero new schema there.

**Tech Stack:** Laravel 11, MySQL (test DB, not sqlite), PHPUnit + `RefreshDatabase`, `auth:api` (JWT) guard.

## Global Constraints

- No shared tables, models, controllers, or routes with `Post`/`UserFeedTopic`/Feed — new code only (per spec: "Dont conflict anything with the feed / post / topics").
- Custom tabs capped at 5 per user (`MAX_CUSTOM_TOPICS = 5`), matching Feed's constant value.
- Filter surface is `DatingPreference` fields only: gender/interested_in, age range, max_distance, relationship_goal — not the full `DatingProfile` field breadth.
- `is_open_to_dating` (new `dating_preferences` column, boolean, default `true`) globally gates every tab — a candidate with it `false` never appears in any Matches Search result, mirroring how `DatingProfile.is_active` already gates the AI Matches workspace.
- Friendship/Long Term/Marriage tabs filter on `DatingPreference.relationship_goal` (`friendship`/`long_term`/`marriage` — 3 of the existing 5 enum values), not on the Friends module's actual connections.
- Response envelope: `{status, message, data, code}` via the existing `ApiResponse` trait — `success()`/`error()`, no new envelope.
- All routes live under the existing `Route::middleware('auth:api')->group(...)` block in `routes/api.php`.

---

### Task 1: Schema — `match_topics` table, `is_open_to_dating` column, models, seeder

**Files:**
- Create: `database/migrations/2026_07_20_100000_create_match_topics_table.php`
- Create: `database/migrations/2026_07_20_100100_add_is_open_to_dating_to_dating_preferences_table.php`
- Create: `app/Models/MatchTopic.php`
- Modify: `app/Models/DatingPreference.php`
- Create: `database/seeders/MatchTopicSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Matches/MatchSchemaTest.php`

**Interfaces:**
- Produces: `App\Models\MatchTopic` (fillable: `user_id, name, slug, icon, tag_keywords, sort_order, is_fixed, is_active`; casts: `tag_keywords => array, sort_order => integer, is_fixed => boolean, is_active => boolean`).
- Produces: `DatingPreference.is_open_to_dating` (boolean, default `true`), added to `$fillable`/`$casts`.
- Produces: `Database\Seeders\MatchTopicSeeder` — creates 6 fixed `MatchTopic` rows with slugs `newest, local, friendship, long_term, marriage, open_to_dating`, each `user_id = null, is_fixed = true, is_active = true`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Matches/MatchSchemaTest.php`:

```php
<?php

namespace Tests\Feature\Matches;

use App\Models\DatingPreference;
use App\Models\MatchTopic;
use App\Models\User;
use Database\Seeders\MatchTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_open_to_dating_defaults_true(): void
    {
        $user = User::factory()->create();

        $preference = DatingPreference::create([
            'user_id' => $user->id,
        ]);

        $this->assertTrue($preference->fresh()->is_open_to_dating);
    }

    public function test_match_topic_seeder_creates_six_fixed_topics(): void
    {
        (new MatchTopicSeeder())->run();

        $this->assertSame(6, MatchTopic::where('is_fixed', true)->whereNull('user_id')->count());

        foreach (['newest', 'local', 'friendship', 'long_term', 'marriage', 'open_to_dating'] as $slug) {
            $this->assertTrue(MatchTopic::where('slug', $slug)->where('is_fixed', true)->exists());
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Matches/MatchSchemaTest.php`
Expected: FAIL — `Class "App\Models\MatchTopic" not found` (or `Database\Seeders\MatchTopicSeeder` not found).

- [ ] **Step 3: Create the migrations and run them**

Create `database/migrations/2026_07_20_100000_create_match_topics_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('icon')->nullable();
            $table->json('tag_keywords')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_fixed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_topics');
    }
};
```

Create `database/migrations/2026_07_20_100100_add_is_open_to_dating_to_dating_preferences_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dating_preferences', function (Blueprint $table) {
            $table->boolean('is_open_to_dating')->default(true)->after('relationship_goal');
        });
    }

    public function down(): void
    {
        Schema::table('dating_preferences', function (Blueprint $table) {
            $table->dropColumn('is_open_to_dating');
        });
    }
};
```

Run: `php artisan migrate`
Expected: both migrations run successfully (no errors).

- [ ] **Step 4: Create the `MatchTopic` model and update `DatingPreference`**

Create `app/Models/MatchTopic.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchTopic extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'icon',
        'tag_keywords',
        'sort_order',
        'is_fixed',
        'is_active',
    ];

    protected $casts = [
        'tag_keywords' => 'array',
        'sort_order'   => 'integer',
        'is_fixed'     => 'boolean',
        'is_active'    => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Modify `app/Models/DatingPreference.php` — add `is_open_to_dating` to both `$fillable` and `$casts`:

```php
    protected $fillable = [
        'user_id',
        'interested_in',
        'min_age',
        'max_age',
        'max_distance',
        'relationship_goal',
        'deal_breakers',
        'partner_preferences',
        'is_open_to_dating',
    ];

    protected $casts = [
        'min_age'           => 'integer',
        'max_age'           => 'integer',
        'max_distance'      => 'integer',
        'interested_in'     => 'string',
        'relationship_goal' => 'string',
        'is_open_to_dating' => 'boolean',
    ];
```

- [ ] **Step 5: Create the seeder and register it**

Create `database/seeders/MatchTopicSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\MatchTopic;
use Illuminate\Database\Seeder;

class MatchTopicSeeder extends Seeder
{
    protected const FIXED_TOPICS = [
        ['slug' => 'newest', 'name' => 'Newest', 'sort_order' => 1],
        ['slug' => 'local', 'name' => 'Local', 'sort_order' => 2],
        ['slug' => 'friendship', 'name' => 'Friendship', 'sort_order' => 3],
        ['slug' => 'long_term', 'name' => 'Long Term', 'sort_order' => 4],
        ['slug' => 'marriage', 'name' => 'Marriage', 'sort_order' => 5],
        ['slug' => 'open_to_dating', 'name' => 'Open to Dating', 'sort_order' => 6],
    ];

    public function run(): void
    {
        foreach (self::FIXED_TOPICS as $topic) {
            MatchTopic::updateOrCreate(
                ['slug' => $topic['slug'], 'user_id' => null],
                [
                    'name'       => $topic['name'],
                    'sort_order' => $topic['sort_order'],
                    'is_fixed'   => true,
                    'is_active'  => true,
                ]
            );
        }
    }
}
```

Modify `database/seeders/DatabaseSeeder.php` — add the import and register the seeder after `DatingProfileSeeder::class`:

```php
use Database\Seeders\MatchTopicSeeder;
```

```php
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            NotificationSeeder::class,
            CompanySettingsSeeder::class,
            DynamicPageSeeder::class,
            WorkspaceSeeder::class,
            AiSuggestedPromptSeeder::class,
            SubscriptionPlanSeeder::class,
            PostSeeder::class,
            ChatSeeder::class,
            DatingProfileSeeder::class,
            MatchTopicSeeder::class,
        ]);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/Matches/MatchSchemaTest.php`
Expected: PASS (2 tests, 2 assertions or more, 0 failures).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_20_100000_create_match_topics_table.php database/migrations/2026_07_20_100100_add_is_open_to_dating_to_dating_preferences_table.php app/Models/MatchTopic.php app/Models/DatingPreference.php database/seeders/MatchTopicSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Matches/MatchSchemaTest.php
git commit -m "feat: add match_topics schema and is_open_to_dating preference column"
```

---

### Task 2: Tabs API (`MatchTopicController`)

**Files:**
- Create: `app/Http/Requests/Matches/StoreMatchTopicRequest.php`
- Create: `app/Http/Resources/MatchTopicResource.php`
- Create: `app/Http/Controllers/API/MatchTopicController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Matches/MatchTopicApiTest.php`

**Interfaces:**
- Consumes: `App\Models\MatchTopic` (Task 1).
- Produces: `GET /api/matches/topics`, `POST /api/matches/topics`, `DELETE /api/matches/topics/{id}` — used by no later task in this plan, but is the tabs source `MatchSearchController` (Task 3) reads via `topic_id`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Matches/MatchTopicApiTest.php`:

```php
<?php

namespace Tests\Feature\Matches;

use App\Models\MatchTopic;
use App\Models\User;
use Database\Seeders\MatchTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchTopicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new MatchTopicSeeder())->run();
    }

    public function test_index_lists_fixed_and_own_custom_topics_only(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        MatchTopic::create(['user_id' => $user->id, 'name' => 'Mine', 'is_fixed' => false]);
        MatchTopic::create(['user_id' => $otherUser->id, 'name' => 'Theirs', 'is_fixed' => false]);

        $response = $this->actingAs($user, 'api')->getJson('/api/matches/topics');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertContains('Mine', $names);
        $this->assertNotContains('Theirs', $names);
        $this->assertCount(7, $names);
    }

    public function test_store_creates_custom_topic(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/matches/topics', ['name' => 'Weekend Hikers']);

        $response->assertOk();
        $this->assertDatabaseHas('match_topics', [
            'user_id'  => $user->id,
            'name'     => 'Weekend Hikers',
            'is_fixed' => false,
        ]);
    }

    public function test_store_rejects_sixth_custom_topic(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            MatchTopic::create(['user_id' => $user->id, 'name' => "Topic {$i}", 'is_fixed' => false]);
        }

        $response = $this->actingAs($user, 'api')->postJson('/api/matches/topics', ['name' => 'One Too Many']);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('match_topics', ['name' => 'One Too Many']);
    }

    public function test_store_rejects_duplicate_name_case_insensitive(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/matches/topics', ['name' => 'NEWEST']);

        $response->assertStatus(422);
    }

    public function test_destroy_removes_own_custom_topic(): void
    {
        $user = User::factory()->create();
        $topic = MatchTopic::create(['user_id' => $user->id, 'name' => 'Mine', 'is_fixed' => false]);

        $response = $this->actingAs($user, 'api')->deleteJson("/api/matches/topics/{$topic->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('match_topics', ['id' => $topic->id]);
    }

    public function test_destroy_rejects_fixed_topic(): void
    {
        $user = User::factory()->create();
        $fixed = MatchTopic::where('is_fixed', true)->first();

        $response = $this->actingAs($user, 'api')->deleteJson("/api/matches/topics/{$fixed->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('match_topics', ['id' => $fixed->id]);
    }

    public function test_destroy_rejects_other_users_topic(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $topic = MatchTopic::create(['user_id' => $otherUser->id, 'name' => 'Theirs', 'is_fixed' => false]);

        $response = $this->actingAs($user, 'api')->deleteJson("/api/matches/topics/{$topic->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('match_topics', ['id' => $topic->id]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Matches/MatchTopicApiTest.php`
Expected: FAIL — 404 Not Found (routes don't exist yet) on every test.

- [ ] **Step 3: Create the request, resource, and controller**

Create `app/Http/Requests/Matches/StoreMatchTopicRequest.php`:

```php
<?php

namespace App\Http\Requests\Matches;

use App\Http\Requests\BaseApiRequest;

class StoreMatchTopicRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
        ];
    }
}
```

Create `app/Http/Resources/MatchTopicResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchTopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'icon'       => $this->icon,
            'is_fixed'   => (bool) $this->is_fixed,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
```

Create `app/Http/Controllers/API/MatchTopicController.php`:

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Matches\StoreMatchTopicRequest;
use App\Http\Resources\MatchTopicResource;
use App\Models\MatchTopic;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class MatchTopicController extends Controller
{
    use ApiResponse;

    protected const MAX_CUSTOM_TOPICS = 5;

    /**
     * Fixed (built-in) tabs plus this user's own custom tabs.
     */
    public function index()
    {
        $userId = Auth::guard('api')->id();

        $topics = MatchTopic::where('is_active', true)
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->orderByDesc('is_fixed')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return $this->success(
            MatchTopicResource::collection($topics),
            'Topics fetched successfully'
        );
    }

    /**
     * Add a new custom tab (max 5 per user, on top of the 6 fixed tabs).
     */
    public function store(StoreMatchTopicRequest $request)
    {
        $userId = Auth::guard('api')->id();

        $count = MatchTopic::where('user_id', $userId)->count();

        if ($count >= self::MAX_CUSTOM_TOPICS) {
            return $this->error(
                [],
                'You have reached the maximum of ' . self::MAX_CUSTOM_TOPICS . ' custom topics.',
                422
            );
        }

        $name = trim($request->validated()['name']);
        $normalized = mb_strtolower($name);

        $exists = MatchTopic::where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->exists();

        if ($exists) {
            return $this->error([], 'A topic with this name already exists.', 422);
        }

        $topic = MatchTopic::create([
            'user_id'  => $userId,
            'name'     => $name,
            'is_fixed' => false,
        ]);

        return $this->success(
            new MatchTopicResource($topic),
            'Topic added successfully'
        );
    }

    /**
     * Remove one of the authenticated user's custom tabs.
     * Fixed tabs have no user_id, so they can never match here.
     */
    public function destroy(int $id)
    {
        $userId = Auth::guard('api')->id();

        $topic = MatchTopic::where('id', $id)->where('user_id', $userId)->first();

        if (!$topic) {
            return $this->error([], 'Topic not found', 404);
        }

        $topic->delete();

        return $this->success([], 'Topic removed successfully');
    }
}
```

- [ ] **Step 4: Wire up the routes**

Modify `routes/api.php` — add the import near the other `API\` controller imports:

```php
use App\Http\Controllers\API\MatchTopicController;
```

Add the route group inside the existing `Route::middleware('auth:api')->group(...)` block, near the Feed Topics group:

```php
    // ── Matches Topics: fixed (built-in) + user-added, unified ────────────────
    Route::controller(MatchTopicController::class)->group(function () {
        Route::get('/matches/topics', 'index');
        Route::post('/matches/topics', 'store');
        Route::delete('/matches/topics/{id}', 'destroy');
    });
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Matches/MatchTopicApiTest.php`
Expected: PASS (7 tests, 0 failures).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Matches/StoreMatchTopicRequest.php app/Http/Resources/MatchTopicResource.php app/Http/Controllers/API/MatchTopicController.php routes/api.php tests/Feature/Matches/MatchTopicApiTest.php
git commit -m "feat: add Matches tabs CRUD API"
```

---

### Task 3: Search/Browse Endpoint (`MatchSearchController`)

**Files:**
- Create: `app/Http/Controllers/API/MatchSearchController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Matches/MatchSearchApiTest.php`

**Interfaces:**
- Consumes: `App\Models\MatchTopic` (Task 1), `App\Models\DatingPreference.is_open_to_dating` (Task 1), the `/matches/topics` list (Task 2, for `topic_id` lookups — not directly called, but its resolver logic is mirrored here).
- Produces: `GET /api/matches/search` — terminal endpoint for this plan.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Matches/MatchSearchApiTest.php`:

```php
<?php

namespace Tests\Feature\Matches;

use App\Models\DatingPreference;
use App\Models\DatingProfile;
use App\Models\MatchTopic;
use App\Models\SavedProfile;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserConnection;
use Database\Seeders\MatchTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchSearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new MatchTopicSeeder())->run();
    }

    protected function makeCandidate(array $profileOverrides = [], array $preferenceOverrides = []): User
    {
        $user = User::factory()->create();

        DatingProfile::create(array_merge([
            'user_id'       => $user->id,
            'dating_gender' => 'female',
            'dating_dob'    => now()->subYears(28)->toDateString(),
            'is_active'     => true,
            'about'         => 'Loves hiking and coffee.',
            'occupation'    => 'Designer',
            'hobbies'       => ['hiking', 'camping'],
        ], $profileOverrides));

        DatingPreference::create(array_merge([
            'user_id'           => $user->id,
            'interested_in'     => 'male',
            'relationship_goal' => 'long_term',
            'is_open_to_dating' => true,
        ], $preferenceOverrides));

        return $user;
    }

    protected function actingUser(array $userOverrides = []): User
    {
        $user = User::factory()->create($userOverrides);

        DatingProfile::create([
            'user_id'       => $user->id,
            'dating_gender' => 'male',
            'is_active'     => true,
        ]);

        DatingPreference::create([
            'user_id'       => $user->id,
            'interested_in' => 'female',
        ]);

        return $user;
    }

    public function test_newest_tab_excludes_opted_out_candidate(): void
    {
        $caller = $this->actingUser();
        $visible = $this->makeCandidate();
        $hidden = $this->makeCandidate(preferenceOverrides: ['is_open_to_dating' => false]);

        $response = $this->actingAs($caller, 'api')->getJson('/api/matches/search?tab=newest');

        $response->assertOk();
        $ids = collect($response->json('data.users'))->pluck('id')->all();

        $this->assertContains($visible->id, $ids);
        $this->assertNotContains($hidden->id, $ids);
    }

    public function test_open_to_dating_tab_excludes_opted_out_candidate(): void
    {
        $caller = $this->actingUser();
        $visible = $this->makeCandidate();
        $hidden = $this->makeCandidate(preferenceOverrides: ['is_open_to_dating' => false]);

        $response = $this->actingAs($caller, 'api')->getJson('/api/matches/search?tab=open_to_dating');

        $response->assertOk();
        $ids = collect($response->json('data.users'))->pluck('id')->all();

        $this->assertContains($visible->id, $ids);
        $this->assertNotContains($hidden->id, $ids);
    }

    public function test_relationship_goal_tabs_filter_correctly(): void
    {
        $caller = $this->actingUser();
        $marriageCandidate = $this->makeCandidate(preferenceOverrides: ['relationship_goal' => 'marriage']);
        $longTermCandidate = $this->makeCandidate(preferenceOverrides: ['relationship_goal' => 'long_term']);

        $response = $this->actingAs($caller, 'api')->getJson('/api/matches/search?tab=marriage');

        $response->assertOk();
        $ids = collect($response->json('data.users'))->pluck('id')->all();

        $this->assertContains($marriageCandidate->id, $ids);
        $this->assertNotContains($longTermCandidate->id, $ids);
    }

    public function test_local_tab_requires_caller_location(): void
    {
        $caller = $this->actingUser();

        $response = $this->actingAs($caller, 'api')->getJson('/api/matches/search?tab=local');

        $response->assertStatus(422);
    }

    public function test_local_tab_returns_nearby_candidate_with_distance(): void
    {
        $caller = $this->actingUser(['latitude' => 30.2672, 'longitude' => -97.7431]);

        $near = $this->makeCandidate();
        $near->update(['latitude' => 30.30, 'longitude' => -97.75]);

        $response = $this->actingAs($caller, 'api')->getJson('/api/matches/search?tab=local');

        $response->assertOk();
        $users = $response->json('data.users');

        $this->assertSame($near->id, $users[0]['id']);
        $this->assertArrayHasKey('distance_km', $users[0]);
    }

    public function test_gender_filter_narrows_results(): void
    {
        $caller = $this->actingUser();
        $female = $this->makeCandidate(['dating_gender' => 'female']);
        $male = $this->makeCandidate(['dating_gender' => 'male']);

        $response = $this->actingAs($caller, 'api')->getJson('/api/matches/search?tab=newest&gender=male');

        $response->assertOk();
        $ids = collect($response->json('data.users'))->pluck('id')->all();

        $this->assertContains($male->id, $ids);
        $this->assertNotContains($female->id, $ids);
    }

    public function test_age_filter_narrows_results(): void
    {
        $caller = $this->actingUser();
        $young = $this->makeCandidate(['dating_dob' => now()->subYears(22)->toDateString()]);
        $old = $this->makeCandidate(['dating_dob' => now()->subYears(45)->toDateString()]);

        $response = $this->actingAs($caller, 'api')->getJson('/api/matches/search?tab=newest&min_age=20&max_age=30');

        $response->assertOk();
        $ids = collect($response->json('data.users'))->pluck('id')->all();

        $this->assertContains($young->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_blocked_users_are_excluded(): void
    {
        $caller = $this->actingUser();
        $blocked = $this->makeCandidate();
        $visible = $this->makeCandidate();

        UserBlock::create(['user_id' => $caller->id, 'blocked_user_id' => $blocked->id]);

        $response = $this->actingAs($caller, 'api')->getJson('/api/matches/search?tab=newest');

        $response->assertOk();
        $ids = collect($response->json('data.users'))->pluck('id')->all();

        $this->assertNotContains($blocked->id, $ids);
        $this->assertContains($visible->id, $ids);
    }

    public function test_relation_status_and_favorite_reflect_state(): void
    {
        $caller = $this->actingUser();
        $friend = $this->makeCandidate();
        $favorite = $this->makeCandidate();

        UserConnection::create([
            'user_one_id'  => $caller->id,
            'user_two_id'  => $friend->id,
            'connected_at' => now(),
        ]);

        SavedProfile::create(['user_id' => $caller->id, 'saved_user_id' => $favorite->id]);

        $response = $this->actingAs($caller, 'api')->getJson('/api/matches/search?tab=newest');

        $response->assertOk();
        $users = collect($response->json('data.users'))->keyBy('id');

        $this->assertSame('connected', $users[$friend->id]['relation_status']);
        $this->assertTrue($users[$favorite->id]['is_favorite']);
    }

    public function test_pagination_shape(): void
    {
        $caller = $this->actingUser();
        $this->makeCandidate();
        $this->makeCandidate();

        $response = $this->actingAs($caller, 'api')->getJson('/api/matches/search?tab=newest&per_page=1');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'users',
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
        $this->assertSame(1, $response->json('data.pagination.per_page'));
        $this->assertSame(2, $response->json('data.pagination.total'));
    }

    public function test_custom_tab_matches_by_keyword(): void
    {
        $caller = $this->actingUser();

        $topic = MatchTopic::create([
            'user_id'  => $caller->id,
            'name'     => 'Hiking',
            'is_fixed' => false,
        ]);

        $hiker = $this->makeCandidate(['hobbies' => ['hiking', 'camping']]);
        $nonHiker = $this->makeCandidate(['hobbies' => ['painting']]);

        $response = $this->actingAs($caller, 'api')->getJson("/api/matches/search?topic_id={$topic->id}");

        $response->assertOk();
        $ids = collect($response->json('data.users'))->pluck('id')->all();

        $this->assertContains($hiker->id, $ids);
        $this->assertNotContains($nonHiker->id, $ids);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Matches/MatchSearchApiTest.php`
Expected: FAIL — 404 Not Found (route doesn't exist yet) on every test.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/API/MatchSearchController.php`:

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ConnectionRequest;
use App\Models\MatchTopic;
use App\Models\SavedProfile;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserConnection;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MatchSearchController extends Controller
{
    use ApiResponse;

    /**
     * Tabbed, filterable browse/search over dating candidates.
     *
     * Query params:
     *   topic_id           integer  — id from GET /matches/topics (fixed or the user's own custom tab)
     *   tab                string   — legacy-alias fixed-tab slug (default: newest)
     *   gender             male|female|both                      (default: caller's DatingPreference.interested_in)
     *   min_age / max_age  integer                                (default: caller's DatingPreference.min_age/max_age)
     *   max_distance       integer km                             (default: caller's DatingPreference.max_distance)
     *   relationship_goal  casual|long_term|marriage|friendship|not_sure  (default: none)
     *   per_page           1-50                                   (default: 15)
     */
    public function search(Request $request)
    {
        $userId = Auth::guard('api')->id();
        $user   = User::find($userId);

        $topicId  = $request->query('topic_id');
        $tabParam = $request->query('tab');
        $perPage  = min(max((int) $request->query('per_page', 15), 1), 50);

        $topic = $this->resolveMatchTopic($userId, $topicId, $tabParam);

        if (!$topic) {
            return $this->error([], 'Topic not found', 404);
        }

        if ($topic->slug === 'local' && (blank($user?->latitude) || blank($user?->longitude))) {
            return $this->error(
                [],
                'Location not set. Please update your location to use the Local tab.',
                422
            );
        }

        $preference = $user?->datingPreference;

        $gender           = $request->query('gender', $preference?->interested_in ?? 'both');
        $minAge           = $request->query('min_age', $preference?->min_age);
        $maxAge           = $request->query('max_age', $preference?->max_age);
        $maxDistance      = $request->query('max_distance', $preference?->max_distance);
        $relationshipGoal = $request->query('relationship_goal');

        $blockedIds = UserBlock::where('user_id', $userId)
            ->orWhere('blocked_user_id', $userId)
            ->get()
            ->flatMap(fn ($b) => [$b->user_id, $b->blocked_user_id])
            ->filter(fn ($id) => $id !== $userId)
            ->unique()
            ->values()
            ->all();

        $query = User::query()
            ->where('users.id', '!=', $userId)
            ->where('users.status', 'active')
            ->whereNotIn('users.id', $blockedIds)
            ->whereHas('datingProfile', fn ($q) => $q->where('is_active', true))
            ->whereHas('datingPreference', function ($q) use ($relationshipGoal) {
                $q->where('is_open_to_dating', true);

                if ($relationshipGoal) {
                    $q->where('relationship_goal', $relationshipGoal);
                }
            })
            ->with(['datingProfile', 'datingPreference']);

        if ($gender !== 'both') {
            $query->whereHas('datingProfile', fn ($q) => $q->where('dating_gender', $gender));
        }

        if ($minAge !== null || $maxAge !== null) {
            $query->whereHas('datingProfile', function ($q) use ($minAge, $maxAge) {
                $q->whereNotNull('dating_dob');

                if ($minAge !== null) {
                    $q->where('dating_dob', '<=', now()->subYears((int) $minAge)->toDateString());
                }

                if ($maxAge !== null) {
                    $q->where('dating_dob', '>=', now()->subYears((int) $maxAge + 1)->addDay()->toDateString());
                }
            });
        }

        if ($maxDistance !== null && filled($user?->latitude) && filled($user?->longitude)) {
            $lat = (float) $user->latitude;
            $lng = (float) $user->longitude;

            $nearbyIds = DB::table('users')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereRaw(
                    '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?',
                    [$lat, $lng, $lat, (float) $maxDistance]
                )
                ->pluck('id')
                ->all();

            $query->whereIn('users.id', $nearbyIds);
        }

        $this->applyTabFilter($query, $topic, $user);

        $candidates = $query->paginate($perPage);

        $connectionIds = UserConnection::forUser($userId)
            ->get()
            ->map(fn ($c) => $c->otherUser($userId)?->id)
            ->filter()
            ->all();

        $sentIds     = ConnectionRequest::where('sender_id', $userId)->pending()->pluck('receiver_id')->all();
        $receivedIds = ConnectionRequest::where('receiver_id', $userId)->pending()->pluck('sender_id')->all();
        $favoriteIds = SavedProfile::where('user_id', $userId)->pluck('saved_user_id')->all();

        $isLocalTab = $topic->slug === 'local';
        $callerLat  = $user?->latitude;
        $callerLng  = $user?->longitude;

        $items = collect($candidates->items())->map(function (User $candidate) use (
            $connectionIds, $sentIds, $receivedIds, $favoriteIds, $isLocalTab, $callerLat, $callerLng
        ) {
            $status = 'none';

            if (in_array($candidate->id, $connectionIds)) {
                $status = 'connected';
            } elseif (in_array($candidate->id, $sentIds)) {
                $status = 'pending_sent';
            } elseif (in_array($candidate->id, $receivedIds)) {
                $status = 'pending_received';
            }

            return $this->formatCandidate(
                $candidate,
                $status,
                in_array($candidate->id, $favoriteIds),
                $isLocalTab,
                $callerLat,
                $callerLng
            );
        });

        return $this->success([
            'users' => $items->values(),
            'pagination' => [
                'current_page' => $candidates->currentPage(),
                'per_page'     => $candidates->perPage(),
                'total'        => $candidates->total(),
                'last_page'    => $candidates->lastPage(),
            ],
        ], $candidates->isEmpty() ? 'No matches found' : 'Matches fetched successfully');
    }

    /**
     * Resolve the requested tab — by id (fixed or the user's own custom tab),
     * or by the legacy 'tab' slug alias, defaulting to 'newest'.
     */
    protected function resolveMatchTopic(int $userId, ?string $topicId, ?string $tab): ?MatchTopic
    {
        if ($topicId) {
            return MatchTopic::where('id', $topicId)
                ->where('is_active', true)
                ->where(function ($q) use ($userId) {
                    $q->whereNull('user_id')->orWhere('user_id', $userId);
                })
                ->first();
        }

        return MatchTopic::where('slug', $tab ?: 'newest')
            ->whereNull('user_id')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Apply the resolved tab's filtering/ordering to the candidate query, in place.
     */
    protected function applyTabFilter($query, MatchTopic $topic, ?User $user): void
    {
        switch ($topic->slug) {
            case 'local':
                $lat = (float) $user->latitude;
                $lng = (float) $user->longitude;

                $query->orderByRaw(
                    '(6371 * acos(cos(radians(?)) * cos(radians(users.latitude)) * cos(radians(users.longitude) - radians(?)) + sin(radians(?)) * sin(radians(users.latitude)))) asc',
                    [$lat, $lng, $lat]
                );
                break;

            case 'friendship':
            case 'long_term':
            case 'marriage':
                $query->whereHas('datingPreference', fn ($q) => $q->where('relationship_goal', $topic->slug));
                $query->latest('users.created_at');
                break;

            case 'open_to_dating':
            case 'newest':
                $query->latest('users.created_at');
                break;

            default:
                // Every custom user tab.
                $topicName = mb_strtolower($topic->name);
                $keywords  = !empty($topic->tag_keywords) ? $topic->tag_keywords : [$topicName];

                $query->whereHas('datingProfile', function ($q) use ($keywords) {
                    $q->where(function ($inner) use ($keywords) {
                        foreach ($keywords as $keyword) {
                            $lower = mb_strtolower($keyword);
                            $inner->orWhere('about', 'like', "%{$lower}%")
                                ->orWhere('occupation', 'like', "%{$lower}%")
                                ->orWhereJsonContains('hobbies', $lower);
                        }
                    });
                });
                $query->latest('users.created_at');
                break;
        }
    }

    protected function formatCandidate(
        User $candidate,
        string $status,
        bool $isFavorite,
        bool $isLocalTab,
        ?string $callerLat,
        ?string $callerLng
    ): array {
        $profile = $candidate->datingProfile;

        $data = [
            'id'                => $candidate->id,
            'avatar'            => asset($candidate->avatar ?? 'user.png'),
            'name'              => $candidate->name,
            'username'          => $candidate->username,
            'age'               => $profile?->dating_dob?->age,
            'city'              => $profile?->dating_location ?? $profile?->city,
            'about'             => $profile?->about ?? $profile?->about_me,
            'occupation'        => $profile?->occupation,
            'relationship_goal' => $candidate->datingPreference?->relationship_goal,
            'relation_status'   => $status,
            'is_favorite'       => $isFavorite,
        ];

        if ($isLocalTab && filled($callerLat) && filled($callerLng) && filled($candidate->latitude) && filled($candidate->longitude)) {
            $data['distance_km'] = round($this->haversineKm(
                (float) $callerLat, (float) $callerLng, (float) $candidate->latitude, (float) $candidate->longitude
            ), 1);
        }

        return $data;
    }

    protected function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
```

- [ ] **Step 4: Wire up the route**

Modify `routes/api.php` — add the import near the other `API\` controller imports:

```php
use App\Http\Controllers\API\MatchSearchController;
```

Add the route inside the existing `Route::middleware('auth:api')->group(...)` block, near the Matches Topics group added in Task 2:

```php
    // ── Matches Search: tabbed, filterable browse of dating candidates ────────
    Route::controller(MatchSearchController::class)->group(function () {
        Route::get('/matches/search', 'search');
    });
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Matches/MatchSearchApiTest.php`
Expected: PASS (10 tests, 0 failures).

- [ ] **Step 6: Run the full Matches test suite together**

Run: `php artisan test --filter=Matches`
Expected: PASS — all tests from `MatchSchemaTest`, `MatchTopicApiTest`, and `MatchSearchApiTest` (19 tests total), 0 failures.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/API/MatchSearchController.php routes/api.php tests/Feature/Matches/MatchSearchApiTest.php
git commit -m "feat: add Matches Search browse/filter API"
```
