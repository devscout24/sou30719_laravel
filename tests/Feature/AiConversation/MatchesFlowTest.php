<?php

namespace Tests\Feature\AiConversation;

use App\Models\AiConversation;
use App\Models\DatingPreference;
use App\Models\DatingProfile;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\MatchCriteriaService;
use App\Services\WorkspaceConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MatchesFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Only sets up the Workspace row and the searching user — does NOT
     * resolve WorkspaceConversationService, since constructor property
     * promotion caches its dependencies at construction time. Every mock a
     * test needs must be set up BEFORE calling enterMatchesWorkspace(),
     * which resolves the service and drives it into the workspace.
     */
    protected function makeMatchesWorkspaceAndUser(): array
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

        return [User::factory()->create()];
    }

    protected function enterMatchesWorkspace(User $user): array
    {
        $service = app(WorkspaceConversationService::class);
        $started = $service->startConversation($user->id);
        $conversation = AiConversation::find($started['conversation_id']);

        $service->handleMessage($conversation, 'Find a match', []);

        return [$service, $conversation->refresh()];
    }

    protected function makeCandidate(string $gender): User
    {
        $candidate = User::factory()->create();

        DatingProfile::create([
            'user_id'       => $candidate->id,
            'dating_gender' => $gender,
            'is_active'     => true,
        ]);

        return $candidate;
    }

    public function test_selecting_male_asks_for_criteria_then_searches_with_ranking(): void
    {
        [$user] = $this->makeMatchesWorkspaceAndUser();
        $candidate = $this->makeCandidate('male');

        $this->mock(MatchCriteriaService::class, function ($mock) {
            $mock->shouldReceive('assessCriteria')
                ->once()
                ->with('tall and athletic')
                ->andReturn(['concrete' => true, 'suggestion' => null]);

            $mock->shouldReceive('rankCandidates')
                ->once()
                ->andReturnUsing(fn ($criteria, $candidates) => $candidates->map(fn ($c) => [
                    'user_id' => $c->id,
                    'score'   => 90,
                    'reason'  => 'Matches your criteria well',
                ])->values()->all());
        });

        [$service, $conversation] = $this->enterMatchesWorkspace($user);

        $service->handleMessage($conversation, 'Male', []);
        $conversation->refresh();

        $this->assertSame('awaiting_match_criteria', $conversation->status);
        $this->assertSame('male', $conversation->match_gender);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('What type of men', $lastMessage->message);

        $service->handleMessage($conversation, 'tall and athletic', []);
        $conversation->refresh();

        $this->assertSame('completed', $conversation->status);
        $this->assertSame('tall and athletic', $conversation->match_criteria);

        $matches = $conversation->messages()->where('type', 'matches')->get()->last();
        $payload = json_decode($matches->message, true);
        $this->assertCount(1, $payload);
        $this->assertSame($candidate->id, $payload[0]['id']);
        $this->assertSame($candidate->id, $payload[0]['user_id']);
        $this->assertArrayHasKey('avatar', $payload[0]);
        $this->assertArrayHasKey('name', $payload[0]);
        $this->assertArrayHasKey('username', $payload[0]);
        $this->assertSame(90, $payload[0]['compatibility_score']);
    }

    public function test_vague_criteria_is_asked_once_more_then_proceeds_regardless(): void
    {
        [$user] = $this->makeMatchesWorkspaceAndUser();
        $this->makeCandidate('female');

        $this->mock(MatchCriteriaService::class, function ($mock) {
            $mock->shouldReceive('assessCriteria')
                ->once()
                ->with('someone nice')
                ->andReturn([
                    'concrete'   => false,
                    'suggestion' => 'try a specific trait like outgoing or family-oriented',
                ]);

            // Second, still-vague reply must NOT trigger a second assessCriteria()
            // call — only one follow-up round, then proceed regardless.
            $mock->shouldReceive('rankCandidates')
                ->once()
                ->andReturn([]);
        });

        [$service, $conversation] = $this->enterMatchesWorkspace($user);
        $service->handleMessage($conversation, 'Female', []);
        $conversation->refresh();

        $service->handleMessage($conversation, 'someone nice', []);
        $conversation->refresh();

        $this->assertSame('awaiting_match_criteria', $conversation->status);
        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('more specific', $lastMessage->message);
        // The AI's tailored suggestion, not the generic fallback text.
        $this->assertStringContainsString('try a specific trait like outgoing or family-oriented', $lastMessage->message);

        $service->handleMessage($conversation, 'still kind of vague', []);
        $conversation->refresh();

        $this->assertSame('completed', $conversation->status);
        $this->assertSame('still kind of vague', $conversation->match_criteria);
    }

    public function test_dating_preference_with_complete_profile_uses_saved_preference_for_search(): void
    {
        [$user] = $this->makeMatchesWorkspaceAndUser();
        $candidate = $this->makeCandidate('female');

        DatingProfile::create([
            'user_id'       => $user->id,
            'dating_gender' => 'male',
            'is_active'     => true,
        ]);
        DatingPreference::create([
            'user_id'             => $user->id,
            'interested_in'       => 'female',
            'partner_preferences' => 'loves hiking',
            'deal_breakers'       => 'smoking',
        ]);

        $this->mock(MatchCriteriaService::class, function ($mock) {
            $mock->shouldReceive('rankCandidates')
                ->once()
                ->with('loves hiking. smoking', \Mockery::type(Collection::class))
                ->andReturn([]);
        });

        [$service, $conversation] = $this->enterMatchesWorkspace($user);
        $service->handleMessage($conversation, 'Dating Preference', []);
        $conversation->refresh();

        $this->assertSame('completed', $conversation->status);
        $this->assertSame('female', $conversation->match_gender);
        $this->assertSame('loves hiking. smoking', $conversation->match_criteria);

        $matches = $conversation->messages()->where('type', 'matches')->get()->last();
        $payload = json_decode($matches->message, true);
        $this->assertCount(1, $payload);
        $this->assertSame($candidate->id, $payload[0]['id']);
    }

    public function test_no_matching_candidates_reports_no_matches(): void
    {
        [$user] = $this->makeMatchesWorkspaceAndUser();
        // No candidates created at all.

        $this->mock(MatchCriteriaService::class, function ($mock) {
            $mock->shouldReceive('assessCriteria')->once()->andReturn(['concrete' => true, 'suggestion' => null]);
        });

        [$service, $conversation] = $this->enterMatchesWorkspace($user);
        $service->handleMessage($conversation, 'Male', []);
        $conversation->refresh();

        $service->handleMessage($conversation, 'anyone friendly', []);
        $conversation->refresh();

        $this->assertSame('completed', $conversation->status);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('No matching found', $lastMessage->message);
    }
}
