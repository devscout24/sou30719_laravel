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
