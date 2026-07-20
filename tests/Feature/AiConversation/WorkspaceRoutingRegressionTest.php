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
        $workspace = Workspace::create([
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

        $this->assertSame($workspace->id, $conversation->workspace_id);
        $this->assertSame('awaiting_match_gender', $conversation->status);

        $pills = $conversation->messages()->where('type', 'pills')->get()->last();
        $this->assertSame(['Male', 'Female', 'Dating Preference'], json_decode($pills->message, true));
    }

    public function test_dating_preference_choice_with_incomplete_profile_bounces_back_to_same_choice(): void
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

        // No DatingProfile/DatingPreference rows set up for this user —
        // hasCompletedDatingProfile() returns false, so choosing "Dating
        // Preference" should bounce back to the same 3-way choice, staying
        // inside the Matches workspace (not idle, not a dead end).
        $service->handleMessage($conversation, 'Dating Preference', []);
        $conversation->refresh();

        $this->assertSame('awaiting_match_gender', $conversation->status);
        $this->assertNotNull($conversation->workspace_id);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString("haven't completed your dating preference", $lastMessage->message);

        $pills = $conversation->messages()->where('type', 'pills')->get()->last();
        $this->assertSame(['Male', 'Female', 'Dating Preference'], json_decode($pills->message, true));
    }
}
