# Marketplace Search API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a tabbed, filterable browse/search API for Marketplace ads (`GET /marketplace/topics` + `GET /marketplace/search`), fully independent of the existing AI ad-creation conversation.

**Architecture:** Mirrors the existing Matches Search module exactly: a new `marketplace_topics` table (5 fixed topics + up to 5 custom per user) drives a topic-scoped filter on top of `Post::ads()->published()->visible()`. Products/Services topics filter on the `ad_type` column exactly; Cosmetics/Electronics/Food and any custom topic filter via keyword `LIKE`/JSON-contains matching against `category`/`title`/`short_description`/`content`/`tags` — same fallback pattern Matches and Feed already use for their own custom tabs. No topic selected = all published ads, newest first. Response reuses the existing `PostResource` unchanged.

**Tech Stack:** Laravel (PHP), MySQL, PHPUnit feature tests with `RefreshDatabase`.

## Global Constraints

- No shared tables, models, controllers, or routes with `Feed`/`UserFeedTopic`/`Matches`/`MatchTopic` — only the pattern is mirrored, per the approved spec.
- `MAX_CUSTOM_TOPICS = 5` per user (same cap as Feed and Matches).
- No changes to `WorkspaceConversationService`, ad creation/editing, or `Post::PRODUCT_CATEGORIES`/`SERVICE_CATEGORIES` — out of scope per spec.
- No new resource for ad cards — reuse `App\Http\Resources\PostResource` as-is.
- Tests run against the project's configured MySQL test database (`php artisan test`), not sqlite, matching this project's existing `phpunit.xml`.
- Full design reference: `docs/superpowers/specs/2026-07-25-marketplace-search-api-design.md`.

---

### Task 1: Schema — `marketplace_topics` table, model, seeder

**Files:**
- Create: `database/migrations/2026_07_25_100000_create_marketplace_topics_table.php`
- Create: `app/Models/MarketplaceTopic.php`
- Create: `database/seeders/MarketplaceTopicSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Marketplace/MarketplaceSchemaTest.php`

**Interfaces:**
- Produces: `App\Models\MarketplaceTopic` (fillable: `user_id, name, slug, icon, tag_keywords, sort_order, is_fixed, is_active`; casts: `tag_keywords` → array, `sort_order` → integer, `is_fixed`/`is_active` → boolean). `Database\Seeders\MarketplaceTopicSeeder::run(): void` seeds 5 rows with `user_id = null`, `is_fixed = true`, `is_active = true`, slugs `products|services|cosmetics|electronics|food`.

- [ ] **Step 1: Write the failing schema test**

Create `tests/Feature/Marketplace/MarketplaceSchemaTest.php`:

```php
<?php

namespace Tests\Feature\Marketplace;

use App\Models\MarketplaceTopic;
use Database\Seeders\MarketplaceTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_topic_seeder_creates_five_fixed_topics(): void
    {
        (new MarketplaceTopicSeeder())->run();

        $this->assertSame(5, MarketplaceTopic::where('is_fixed', true)->whereNull('user_id')->count());

        foreach (['products', 'services', 'cosmetics', 'electronics', 'food'] as $slug) {
            $this->assertTrue(MarketplaceTopic::where('slug', $slug)->where('is_fixed', true)->exists());
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MarketplaceSchemaTest`
Expected: FAIL — `Class "Database\Seeders\MarketplaceTopicSeeder" not found` (or similar autoload error).

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_25_100000_create_marketplace_topics_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_topics', function (Blueprint $table) {
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
        Schema::dropIfExists('marketplace_topics');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/MarketplaceTopic.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceTopic extends Model
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

- [ ] **Step 5: Create the seeder**

Create `database/seeders/MarketplaceTopicSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\MarketplaceTopic;
use Illuminate\Database\Seeder;

class MarketplaceTopicSeeder extends Seeder
{
    protected const FIXED_TOPICS = [
        ['slug' => 'products', 'name' => 'Products', 'sort_order' => 1],
        ['slug' => 'services', 'name' => 'Services', 'sort_order' => 2],
        ['slug' => 'cosmetics', 'name' => 'Cosmetics', 'sort_order' => 3],
        ['slug' => 'electronics', 'name' => 'Electronics', 'sort_order' => 4],
        ['slug' => 'food', 'name' => 'Food', 'sort_order' => 5],
    ];

    public function run(): void
    {
        foreach (self::FIXED_TOPICS as $topic) {
            MarketplaceTopic::updateOrCreate(
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

- [ ] **Step 6: Register the seeder in `DatabaseSeeder`**

Modify `database/seeders/DatabaseSeeder.php` — add the import after `use Database\Seeders\MatchTopicSeeder;`:

```php
use Database\Seeders\MatchTopicSeeder;
use Database\Seeders\MarketplaceTopicSeeder;
```

And add the call after `MatchTopicSeeder::class,` in the `$this->call([...])` list:

```php
            DatingProfileSeeder::class,
            MatchTopicSeeder::class,
            MarketplaceTopicSeeder::class,
        ]);
```

- [ ] **Step 7: Run the migration and test to verify it passes**

Run: `php artisan migrate`
Expected: `2026_07_25_100000_create_marketplace_topics_table` migrates successfully.

Run: `php artisan test --filter=MarketplaceSchemaTest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_07_25_100000_create_marketplace_topics_table.php app/Models/MarketplaceTopic.php database/seeders/MarketplaceTopicSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Marketplace/MarketplaceSchemaTest.php
git commit -m "feat: add marketplace_topics schema, model, and seeder"
```

---

### Task 2: Marketplace Topics API

**Files:**
- Create: `app/Http/Requests/Marketplace/StoreMarketplaceTopicRequest.php`
- Create: `app/Http/Resources/MarketplaceTopicResource.php`
- Create: `app/Http/Controllers/API/MarketplaceTopicController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Marketplace/MarketplaceTopicApiTest.php`

**Interfaces:**
- Consumes: `App\Models\MarketplaceTopic` (Task 1).
- Produces: `MarketplaceTopicController@index(): JsonResponse`, `@store(StoreMarketplaceTopicRequest $request): JsonResponse`, `@destroy(int $id): JsonResponse`. Routes `GET/POST /api/marketplace/topics`, `DELETE /api/marketplace/topics/{id}`. `MarketplaceTopicResource` shape: `{id, name, slug, icon, is_fixed, created_at}`.

- [ ] **Step 1: Write the failing topics API test**

Create `tests/Feature/Marketplace/MarketplaceTopicApiTest.php`:

```php
<?php

namespace Tests\Feature\Marketplace;

use App\Models\MarketplaceTopic;
use App\Models\User;
use Database\Seeders\MarketplaceTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceTopicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new MarketplaceTopicSeeder())->run();
    }

    public function test_index_lists_fixed_and_own_custom_topics_only(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        MarketplaceTopic::create(['user_id' => $user->id, 'name' => 'Mine', 'is_fixed' => false]);
        MarketplaceTopic::create(['user_id' => $otherUser->id, 'name' => 'Theirs', 'is_fixed' => false]);

        $response = $this->actingAs($user, 'api')->getJson('/api/marketplace/topics');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertContains('Mine', $names);
        $this->assertNotContains('Theirs', $names);
        $this->assertCount(6, $names);
    }

    public function test_store_creates_custom_topic(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/marketplace/topics', ['name' => 'Weekend Sales']);

        $response->assertOk();
        $this->assertDatabaseHas('marketplace_topics', [
            'user_id'  => $user->id,
            'name'     => 'Weekend Sales',
            'is_fixed' => false,
        ]);
    }

    public function test_store_rejects_sixth_custom_topic(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            MarketplaceTopic::create(['user_id' => $user->id, 'name' => "Topic {$i}", 'is_fixed' => false]);
        }

        $response = $this->actingAs($user, 'api')->postJson('/api/marketplace/topics', ['name' => 'One Too Many']);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('marketplace_topics', ['name' => 'One Too Many']);
    }

    public function test_store_rejects_duplicate_name_case_insensitive(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/marketplace/topics', ['name' => 'PRODUCTS']);

        $response->assertStatus(422);
    }

    public function test_destroy_removes_own_custom_topic(): void
    {
        $user = User::factory()->create();
        $topic = MarketplaceTopic::create(['user_id' => $user->id, 'name' => 'Mine', 'is_fixed' => false]);

        $response = $this->actingAs($user, 'api')->deleteJson("/api/marketplace/topics/{$topic->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('marketplace_topics', ['id' => $topic->id]);
    }

    public function test_destroy_rejects_fixed_topic(): void
    {
        $user = User::factory()->create();
        $fixed = MarketplaceTopic::where('is_fixed', true)->first();

        $response = $this->actingAs($user, 'api')->deleteJson("/api/marketplace/topics/{$fixed->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('marketplace_topics', ['id' => $fixed->id]);
    }

    public function test_destroy_rejects_other_users_topic(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $topic = MarketplaceTopic::create(['user_id' => $otherUser->id, 'name' => 'Theirs', 'is_fixed' => false]);

        $response = $this->actingAs($user, 'api')->deleteJson("/api/marketplace/topics/{$topic->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('marketplace_topics', ['id' => $topic->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MarketplaceTopicApiTest`
Expected: FAIL — 404/route-not-found on `/api/marketplace/topics` (route doesn't exist yet).

- [ ] **Step 3: Create the store request**

Create `app/Http/Requests/Marketplace/StoreMarketplaceTopicRequest.php`:

```php
<?php

namespace App\Http\Requests\Marketplace;

use App\Http\Requests\BaseApiRequest;

class StoreMarketplaceTopicRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
        ];
    }
}
```

- [ ] **Step 4: Create the resource**

Create `app/Http/Resources/MarketplaceTopicResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceTopicResource extends JsonResource
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

- [ ] **Step 5: Create the controller**

Create `app/Http/Controllers/API/MarketplaceTopicController.php`:

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreMarketplaceTopicRequest;
use App\Http\Resources\MarketplaceTopicResource;
use App\Models\MarketplaceTopic;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class MarketplaceTopicController extends Controller
{
    use ApiResponse;

    protected const MAX_CUSTOM_TOPICS = 5;

    /**
     * The unified marketplace-topics list: the fixed (built-in) topics every
     * user shares, followed by this user's own custom topics.
     */
    public function index()
    {
        $userId = Auth::guard('api')->id();

        $topics = MarketplaceTopic::where('is_active', true)
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->orderByDesc('is_fixed')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return $this->success(
            MarketplaceTopicResource::collection($topics),
            'Topics fetched successfully'
        );
    }

    /**
     * Add a new custom topic (max 5 per user, on top of the fixed topics).
     */
    public function store(StoreMarketplaceTopicRequest $request)
    {
        $userId = Auth::guard('api')->id();

        $count = MarketplaceTopic::where('user_id', $userId)->count();

        if ($count >= self::MAX_CUSTOM_TOPICS) {
            return $this->error(
                [],
                'You have reached the maximum of ' . self::MAX_CUSTOM_TOPICS . ' custom topics.',
                422
            );
        }

        $name = trim($request->validated()['name']);
        $normalized = mb_strtolower($name);

        // Case-insensitive duplicate check against both the fixed topics and this user's own
        $exists = MarketplaceTopic::where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->exists();

        if ($exists) {
            return $this->error([], 'A topic with this name already exists.', 422);
        }

        $topic = MarketplaceTopic::create([
            'user_id'  => $userId,
            'name'     => $name,
            'is_fixed' => false,
        ]);

        return $this->success(
            new MarketplaceTopicResource($topic),
            'Topic added successfully'
        );
    }

    /**
     * Remove one of the authenticated user's custom topics.
     * Fixed topics have no user_id, so they can never match here.
     */
    public function destroy(int $id)
    {
        $userId = Auth::guard('api')->id();

        $topic = MarketplaceTopic::where('id', $id)->where('user_id', $userId)->first();

        if (!$topic) {
            return $this->error([], 'Topic not found', 404);
        }

        $topic->delete();

        return $this->success([], 'Topic removed successfully');
    }
}
```

- [ ] **Step 6: Wire the routes**

Modify `routes/api.php` — add the import alphabetically before the `MatchSearchController` import (line 12):

```php
use App\Http\Controllers\API\MarketplaceTopicController;
use App\Http\Controllers\API\MatchSearchController;
```

Then add a new route group right after the existing "Matches Search" block:

```php
    // ── Matches Search: tabbed, filterable browse of dating candidates ────────
    Route::controller(MatchSearchController::class)->group(function () {
        Route::get('/matches/search', 'search');
    });

    // ── Marketplace Topics: fixed (built-in) + user-added, unified ────────────
    Route::controller(MarketplaceTopicController::class)->group(function () {
        Route::get('/marketplace/topics', 'index');
        Route::post('/marketplace/topics', 'store');
        Route::delete('/marketplace/topics/{id}', 'destroy');
    });
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=MarketplaceTopicApiTest`
Expected: PASS (7 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Marketplace/StoreMarketplaceTopicRequest.php app/Http/Resources/MarketplaceTopicResource.php app/Http/Controllers/API/MarketplaceTopicController.php routes/api.php tests/Feature/Marketplace/MarketplaceTopicApiTest.php
git commit -m "feat: add Marketplace topics API"
```

---

### Task 3: Marketplace Search API

**Files:**
- Create: `app/Http/Controllers/API/MarketplaceSearchController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Marketplace/MarketplaceSearchApiTest.php`

**Interfaces:**
- Consumes: `App\Models\MarketplaceTopic` (Task 1); `App\Models\Post` scopes `ads()`, `published()`, `visible()` (already exist); `App\Http\Resources\PostResource` (already exists, unchanged).
- Produces: `MarketplaceSearchController@search(Request $request): JsonResponse`. Route `GET /api/marketplace/search`.

- [ ] **Step 1: Write the failing search API test**

Create `tests/Feature/Marketplace/MarketplaceSearchApiTest.php`:

```php
<?php

namespace Tests\Feature\Marketplace;

use App\Models\MarketplaceTopic;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBlock;
use Database\Seeders\MarketplaceTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceSearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new MarketplaceTopicSeeder())->run();
    }

    protected function makeAd(array $overrides = []): Post
    {
        return Post::create(array_merge([
            'user_id'      => User::factory()->create()->id,
            'type'         => 'ad',
            'ad_type'      => 'product',
            'category'     => 'other',
            'price'        => 50,
            'status'       => 'published',
            'visibility'   => 'public',
            'published_at' => now(),
        ], $overrides));
    }

    public function test_no_topic_returns_all_published_ads_newest_first(): void
    {
        $caller = User::factory()->create();
        $product = $this->makeAd(['ad_type' => 'product', 'published_at' => now()->subDay()]);
        $service = $this->makeAd(['ad_type' => 'service', 'published_at' => now()]);

        $response = $this->actingAs($caller, 'api')->getJson('/api/marketplace/search');

        $response->assertOk();
        $ids = collect($response->json('data.posts'))->pluck('id')->all();

        $this->assertContains($product->id, $ids);
        $this->assertContains($service->id, $ids);
        $this->assertSame($service->id, $ids[0]);
    }

    public function test_products_tab_filters_by_ad_type_exactly(): void
    {
        $caller = User::factory()->create();
        $product = $this->makeAd(['ad_type' => 'product']);
        $service = $this->makeAd(['ad_type' => 'service']);

        $response = $this->actingAs($caller, 'api')->getJson('/api/marketplace/search?tab=products');

        $response->assertOk();
        $ids = collect($response->json('data.posts'))->pluck('id')->all();

        $this->assertContains($product->id, $ids);
        $this->assertNotContains($service->id, $ids);
    }

    public function test_services_tab_filters_by_ad_type_exactly(): void
    {
        $caller = User::factory()->create();
        $product = $this->makeAd(['ad_type' => 'product']);
        $service = $this->makeAd(['ad_type' => 'service']);

        $response = $this->actingAs($caller, 'api')->getJson('/api/marketplace/search?tab=services');

        $response->assertOk();
        $ids = collect($response->json('data.posts'))->pluck('id')->all();

        $this->assertContains($service->id, $ids);
        $this->assertNotContains($product->id, $ids);
    }

    public function test_cosmetics_tab_matches_by_keyword(): void
    {
        $caller = User::factory()->create();
        $cosmetic = $this->makeAd(['category' => 'beauty', 'title' => 'Lipstick set']);
        $other = $this->makeAd(['category' => 'other', 'title' => 'Garden hose']);

        $response = $this->actingAs($caller, 'api')->getJson('/api/marketplace/search?tab=cosmetics');

        $response->assertOk();
        $ids = collect($response->json('data.posts'))->pluck('id')->all();

        $this->assertContains($cosmetic->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_electronics_tab_matches_by_keyword(): void
    {
        $caller = User::factory()->create();
        $electronic = $this->makeAd(['category' => 'electronics']);
        $other = $this->makeAd(['category' => 'home_furniture']);

        $response = $this->actingAs($caller, 'api')->getJson('/api/marketplace/search?tab=electronics');

        $response->assertOk();
        $ids = collect($response->json('data.posts'))->pluck('id')->all();

        $this->assertContains($electronic->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_food_tab_matches_by_keyword(): void
    {
        $caller = User::factory()->create();
        $food = $this->makeAd(['category' => 'other', 'short_description' => 'Fresh organic food basket']);
        $other = $this->makeAd(['category' => 'other', 'short_description' => 'Used bicycle for sale']);

        $response = $this->actingAs($caller, 'api')->getJson('/api/marketplace/search?tab=food');

        $response->assertOk();
        $ids = collect($response->json('data.posts'))->pluck('id')->all();

        $this->assertContains($food->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_non_ad_unpublished_and_private_posts_are_excluded(): void
    {
        $caller = User::factory()->create();
        $ad = $this->makeAd();

        $regularPost = Post::create([
            'user_id'    => User::factory()->create()->id,
            'type'       => 'regular',
            'status'     => 'published',
            'visibility' => 'public',
        ]);

        $eventPost = Post::create([
            'user_id'    => User::factory()->create()->id,
            'type'       => 'event',
            'status'     => 'published',
            'visibility' => 'public',
        ]);

        $draftAd = $this->makeAd(['status' => 'draft']);
        $privateAd = $this->makeAd(['visibility' => 'private']);

        $response = $this->actingAs($caller, 'api')->getJson('/api/marketplace/search');

        $response->assertOk();
        $ids = collect($response->json('data.posts'))->pluck('id')->all();

        $this->assertContains($ad->id, $ids);
        $this->assertNotContains($regularPost->id, $ids);
        $this->assertNotContains($eventPost->id, $ids);
        $this->assertNotContains($draftAd->id, $ids);
        $this->assertNotContains($privateAd->id, $ids);
    }

    public function test_blocked_users_ads_are_excluded(): void
    {
        $caller = User::factory()->create();
        $blockedUser = User::factory()->create();
        $blockedAd = $this->makeAd(['user_id' => $blockedUser->id]);
        $visibleAd = $this->makeAd();

        UserBlock::create(['user_id' => $caller->id, 'blocked_user_id' => $blockedUser->id]);

        $response = $this->actingAs($caller, 'api')->getJson('/api/marketplace/search');

        $response->assertOk();
        $ids = collect($response->json('data.posts'))->pluck('id')->all();

        $this->assertNotContains($blockedAd->id, $ids);
        $this->assertContains($visibleAd->id, $ids);
    }

    public function test_price_filters_narrow_results(): void
    {
        $caller = User::factory()->create();
        $cheap = $this->makeAd(['price' => 10]);
        $expensive = $this->makeAd(['price' => 500]);

        $response = $this->actingAs($caller, 'api')->getJson('/api/marketplace/search?min_price=100&max_price=1000');

        $response->assertOk();
        $ids = collect($response->json('data.posts'))->pluck('id')->all();

        $this->assertContains($expensive->id, $ids);
        $this->assertNotContains($cheap->id, $ids);
    }

    public function test_max_distance_requires_caller_location(): void
    {
        $caller = User::factory()->create();

        $response = $this->actingAs($caller, 'api')->getJson('/api/marketplace/search?max_distance=50');

        $response->assertStatus(422);
    }

    public function test_max_distance_filters_to_nearby_ad_posters(): void
    {
        $caller = User::factory()->create(['latitude' => 30.2672, 'longitude' => -97.7431]);

        $nearOwner = User::factory()->create(['latitude' => 30.30, 'longitude' => -97.75]);
        $near = $this->makeAd(['user_id' => $nearOwner->id]);

        $farOwner = User::factory()->create(['latitude' => 48.8566, 'longitude' => 2.3522]);
        $far = $this->makeAd(['user_id' => $farOwner->id]);

        $response = $this->actingAs($caller, 'api')->getJson('/api/marketplace/search?max_distance=50');

        $response->assertOk();
        $ids = collect($response->json('data.posts'))->pluck('id')->all();

        $this->assertContains($near->id, $ids);
        $this->assertNotContains($far->id, $ids);
    }

    public function test_custom_topic_matches_by_keyword(): void
    {
        $caller = User::factory()->create();

        $topic = MarketplaceTopic::create([
            'user_id'  => $caller->id,
            'name'     => 'Furniture',
            'is_fixed' => false,
        ]);

        $matching = $this->makeAd(['category' => 'home_furniture', 'title' => 'Oak dining furniture set']);
        $nonMatching = $this->makeAd(['category' => 'other', 'title' => 'Mountain bike']);

        $response = $this->actingAs($caller, 'api')->getJson("/api/marketplace/search?topic_id={$topic->id}");

        $response->assertOk();
        $ids = collect($response->json('data.posts'))->pluck('id')->all();

        $this->assertContains($matching->id, $ids);
        $this->assertNotContains($nonMatching->id, $ids);
    }

    public function test_pagination_shape(): void
    {
        $caller = User::factory()->create();
        $this->makeAd();
        $this->makeAd();

        $response = $this->actingAs($caller, 'api')->getJson('/api/marketplace/search?per_page=1');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'posts',
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
            ],
        ]);
        $this->assertSame(1, $response->json('data.pagination.per_page'));
        $this->assertSame(2, $response->json('data.pagination.total'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MarketplaceSearchApiTest`
Expected: FAIL — 404/route-not-found on `/api/marketplace/search` (route doesn't exist yet).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/API/MarketplaceSearchController.php`:

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\MarketplaceTopic;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBlock;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MarketplaceSearchController extends Controller
{
    use ApiResponse;

    /**
     * Tabbed, filterable browse/search over published marketplace ads.
     *
     * Query params:
     *   topic_id            integer — id from GET /marketplace/topics (fixed or the user's own custom topic)
     *   tab                 string  — legacy-alias fixed-topic slug (no default: omitted means "all ads")
     *   min_price/max_price decimal
     *   max_distance        integer km — requires the caller to have latitude/longitude set
     *   per_page            1-50 (default: 15)
     */
    public function search(Request $request)
    {
        $userId = Auth::guard('api')->id();
        $user   = User::find($userId);

        $topicId  = $request->query('topic_id');
        $tabParam = $request->query('tab');
        $perPage  = min(max((int) $request->query('per_page', 15), 1), 50);

        $topic = $this->resolveMarketplaceTopic($userId, $topicId, $tabParam);

        if (($topicId || $tabParam) && !$topic) {
            return $this->error([], 'Topic not found', 404);
        }

        $minPrice    = $request->query('min_price');
        $maxPrice    = $request->query('max_price');
        $maxDistance = $request->query('max_distance');

        if ($maxDistance !== null && (blank($user?->latitude) || blank($user?->longitude))) {
            return $this->error(
                [],
                'Location not set. Please update your location to use the max_distance filter.',
                422
            );
        }

        $blockedIds = UserBlock::where('user_id', $userId)
            ->orWhere('blocked_user_id', $userId)
            ->get()
            ->flatMap(fn ($b) => [$b->user_id, $b->blocked_user_id])
            ->filter(fn ($id) => $id !== $userId)
            ->unique()
            ->values()
            ->all();

        $query = Post::query()
            ->ads()
            ->published()
            ->visible()
            ->whereNotIn('user_id', $blockedIds)
            ->with(['user', 'images', 'workspace'])
            ->withCount(['likes', 'shares'])
            ->withExists(['likes as is_liked' => fn ($q) => $q->where('user_id', $userId)]);

        if ($minPrice !== null) {
            $query->where('price', '>=', (float) $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        if ($maxDistance !== null) {
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

            $query->whereIn('user_id', $nearbyIds);
        }

        if ($topic) {
            $this->applyTopicFilter($query, $topic);
        }

        $posts = $query->latest('published_at')->paginate($perPage);

        return $this->success([
            'posts' => PostResource::collection($posts->items()),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'per_page'     => $posts->perPage(),
                'total'        => $posts->total(),
                'last_page'    => $posts->lastPage(),
            ],
        ], $posts->isEmpty() ? 'No ads found' : 'Ads fetched successfully');
    }

    /**
     * Resolve the requested topic — by id (fixed or the user's own custom
     * topic), or by the legacy 'tab' slug alias. Returns null when neither is
     * given (the "All Ads" view) or when the given id/slug doesn't resolve.
     */
    protected function resolveMarketplaceTopic(int $userId, ?string $topicId, ?string $tab): ?MarketplaceTopic
    {
        if ($topicId) {
            return MarketplaceTopic::where('id', $topicId)
                ->where('is_active', true)
                ->where(function ($q) use ($userId) {
                    $q->whereNull('user_id')->orWhere('user_id', $userId);
                })
                ->first();
        }

        if ($tab) {
            return MarketplaceTopic::where('slug', $tab)
                ->whereNull('user_id')
                ->where('is_active', true)
                ->first();
        }

        return null;
    }

    /**
     * Apply the resolved topic's filtering to the ad query, in place.
     */
    protected function applyTopicFilter($query, MarketplaceTopic $topic): void
    {
        switch ($topic->slug) {
            case 'products':
                $query->where('ad_type', 'product');
                break;

            case 'services':
                $query->where('ad_type', 'service');
                break;

            case 'cosmetics':
                $this->applyKeywordFilter($query, ['cosmetics', 'beauty', 'skincare', 'makeup']);
                break;

            case 'electronics':
                $this->applyKeywordFilter($query, ['electronics', 'gadget', 'tech']);
                break;

            case 'food':
                $this->applyKeywordFilter($query, ['food', 'grocery', 'restaurant', 'meal']);
                break;

            default:
                // Every custom user topic.
                $keywords = !empty($topic->tag_keywords) ? $topic->tag_keywords : [mb_strtolower($topic->name)];
                $this->applyKeywordFilter($query, $keywords);
                break;
        }
    }

    /**
     * @param  string[]  $keywords
     */
    protected function applyKeywordFilter($query, array $keywords): void
    {
        $query->where(function ($q) use ($keywords) {
            foreach ($keywords as $keyword) {
                $kw = mb_strtolower(trim($keyword));

                if (blank($kw)) {
                    continue;
                }

                $q->orWhere('category', 'like', "%{$kw}%")
                  ->orWhere('title', 'like', "%{$kw}%")
                  ->orWhere('short_description', 'like', "%{$kw}%")
                  ->orWhere('content', 'like', "%{$kw}%")
                  ->orWhereJsonContains('tags', $kw);
            }
        });
    }
}
```

- [ ] **Step 4: Wire the route**

Modify `routes/api.php` — add the import alphabetically before `MarketplaceTopicController` (which comes before `MatchSearchController` — Task 2, Step 6):

```php
use App\Http\Controllers\API\MarketplaceSearchController;
use App\Http\Controllers\API\MarketplaceTopicController;
use App\Http\Controllers\API\MatchSearchController;
```

Then add a new route group right after the "Marketplace Topics" block added in Task 2:

```php
    // ── Marketplace Topics: fixed (built-in) + user-added, unified ────────────
    Route::controller(MarketplaceTopicController::class)->group(function () {
        Route::get('/marketplace/topics', 'index');
        Route::post('/marketplace/topics', 'store');
        Route::delete('/marketplace/topics/{id}', 'destroy');
    });

    // ── Marketplace Search: tabbed, filterable browse of ads ───────────────────
    Route::controller(MarketplaceSearchController::class)->group(function () {
        Route::get('/marketplace/search', 'search');
    });
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=MarketplaceSearchApiTest`
Expected: PASS (13 tests)

- [ ] **Step 6: Run the full Marketplace suite together**

Run: `php artisan test --filter=Marketplace`
Expected: PASS (all of `MarketplaceSchemaTest`, `MarketplaceTopicApiTest`, `MarketplaceSearchApiTest`)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/API/MarketplaceSearchController.php routes/api.php tests/Feature/Marketplace/MarketplaceSearchApiTest.php
git commit -m "feat: add Marketplace search API"
```
