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
