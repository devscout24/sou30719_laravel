<?php

namespace Tests\Feature\AiConversation;

use App\Models\AiConversation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\SocialPostCollectorService;
use App\Services\WorkspaceConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceEntryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSocialPostWorkspace(): Workspace
    {
        return Workspace::create([
            'title'        => 'Social Post',
            'description'  => 'Share a post.',
            'prompt'       => 'Share a post',
            'slug'         => Workspace::SLUG_SOCIAL_POST,
            'is_supported' => true,
            'status'       => 'active',
            'sort_order'   => 1,
        ]);
    }

    public function test_free_text_at_idle_is_ignored_and_pills_are_reshown(): void
    {
        $this->makeSocialPostWorkspace();
        $user = User::factory()->create();

        $service = app(WorkspaceConversationService::class);
        $started = $service->startConversation($user->id);
        $conversation = AiConversation::find($started['conversation_id']);

        $service->handleMessage($conversation, 'I want to make a post about my trip', []);
        $conversation->refresh();

        $this->assertSame('idle', $conversation->status);
        $this->assertNull($conversation->workspace_id);

        // messages() bakes in an ascending order (oldest-first, for transcript
        // display); ->latest() can't override that (Eloquent orderBy calls
        // append rather than replace), so grab the last element instead.
        $lastPills = $conversation->messages()->where('type', 'pills')->get()->last();
        $this->assertNotNull($lastPills);
        $this->assertSame(['Share a post'], json_decode($lastPills->message, true));
    }

    public function test_exact_pill_text_assigns_workspace_directly_without_confirmation(): void
    {
        $workspace = $this->makeSocialPostWorkspace();
        $user = User::factory()->create();

        // Entering the Social Post workspace now generates 6 AI topic-suggestion
        // pills — mock it so this test doesn't make a real OpenAI HTTP call.
        $this->mock(SocialPostCollectorService::class, function ($mock) {
            $mock->shouldReceive('suggestTopics')
                ->once()
                ->andReturn(['Food', 'Travel', 'Nature', 'Pets', 'Fitness', 'Art']);
        });

        $service = app(WorkspaceConversationService::class);
        $started = $service->startConversation($user->id);
        $conversation = AiConversation::find($started['conversation_id']);

        $service->handleMessage($conversation, 'Share a post', []);
        $conversation->refresh();

        $this->assertSame($workspace->id, $conversation->workspace_id);
        $this->assertSame('collecting', $conversation->status);
    }

    public function test_blank_text_at_idle_shows_pills(): void
    {
        $this->makeSocialPostWorkspace();
        $user = User::factory()->create();

        $service = app(WorkspaceConversationService::class);
        $started = $service->startConversation($user->id);
        $conversation = AiConversation::find($started['conversation_id']);

        $service->handleMessage($conversation, null, []);
        $conversation->refresh();

        $this->assertSame('idle', $conversation->status);
    }
}
