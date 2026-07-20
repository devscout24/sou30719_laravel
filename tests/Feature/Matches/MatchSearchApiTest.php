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
        $nonHiker = $this->makeCandidate([
            'hobbies' => ['painting'],
            'about'   => 'Enjoys painting and museums.',
        ]);

        $response = $this->actingAs($caller, 'api')->getJson("/api/matches/search?topic_id={$topic->id}");

        $response->assertOk();
        $ids = collect($response->json('data.users'))->pluck('id')->all();

        $this->assertContains($hiker->id, $ids);
        $this->assertNotContains($nonHiker->id, $ids);
    }
}
