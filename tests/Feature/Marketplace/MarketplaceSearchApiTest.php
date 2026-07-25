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
