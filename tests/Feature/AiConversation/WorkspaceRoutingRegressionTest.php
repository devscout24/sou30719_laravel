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

        // No dating profile set up — enterMatchesWorkspace() bounces back to
        // idle with the "complete your profile" message. That bounce only
        // happens if assignWorkspace() correctly routed into the Matches
        // branch, which is what this test is verifying still works.
        $this->assertSame('idle', $conversation->status);
        $this->assertNull($conversation->workspace_id);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('complete your dating preference', $lastMessage->message);
    }
}
