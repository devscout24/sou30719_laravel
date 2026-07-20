<?php

namespace Tests\Feature\AiConversation;

use App\Models\AiConversation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\PostCuratorService;
use App\Services\AI\SocialPostCollectorService;
use App\Services\WorkspaceConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialPostCollectionTest extends TestCase
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

    /**
     * Mocks are container-resolved once, at the moment WorkspaceConversationService
     * is constructed (constructor property promotion caches the dependency on the
     * object) — so every mock a test needs, for every phase it will drive the
     * conversation through, must be set up before this is called. Returns the
     * service plus a fresh conversation sitting in Phase 1 (topic hint), having
     * already selected the Social Post workspace.
     */
    protected function enterSocialPostWorkspace(): array
    {
        $this->makeSocialPostWorkspace();
        $user = User::factory()->create();

        $service = app(WorkspaceConversationService::class);
        $started = $service->startConversation($user->id);
        $conversation = AiConversation::find($started['conversation_id']);

        $service->handleMessage($conversation, 'Share a post', []);

        return [$service, $conversation->refresh()];
    }

    protected function mockTopicSuggestions(): void
    {
        $this->mock(SocialPostCollectorService::class, function ($mock) {
            $mock->shouldReceive('suggestTopics')
                ->once()
                ->andReturn(['Food', 'Travel', 'Nature', 'Pets', 'Fitness', 'Art']);
        });
    }

    public function test_entering_workspace_asks_topic_and_shows_ai_suggested_pills(): void
    {
        $this->mockTopicSuggestions();
        [, $conversation] = $this->enterSocialPostWorkspace();

        $this->assertSame('collecting', $conversation->status);
        $this->assertNull($conversation->topic);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertSame('What is your post about?', $lastMessage->message);

        $pills = $conversation->messages()->where('type', 'pills')->get()->last();
        $this->assertSame(['Food', 'Travel', 'Nature', 'Pets', 'Fitness', 'Art'], json_decode($pills->message, true));
    }

    public function test_blank_reply_at_topic_hint_reasks_the_question(): void
    {
        $this->mockTopicSuggestions();
        [$service, $conversation] = $this->enterSocialPostWorkspace();

        $service->handleMessage($conversation, null, []);
        $conversation->refresh();

        $this->assertSame('collecting', $conversation->status);
        $this->assertNull($conversation->topic);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertSame('What is your post about?', $lastMessage->message);
    }

    public function test_topic_hint_reply_moves_to_details_prompt(): void
    {
        $this->mockTopicSuggestions();
        [$service, $conversation] = $this->enterSocialPostWorkspace();

        $service->handleMessage($conversation, 'trees or forest', []);
        $conversation->refresh();

        $this->assertSame('collecting', $conversation->status);
        $this->assertSame('trees or forest', $conversation->topic);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertSame('Please give me a description and a photo for your post.', $lastMessage->message);
    }

    public function test_image_without_description_asks_for_description(): void
    {
        $this->mockTopicSuggestions();
        [$service, $conversation] = $this->enterSocialPostWorkspace();
        $service->handleMessage($conversation, 'trees', []);
        $conversation->refresh();

        $service->handleMessage($conversation, null, ['posts/photo1.jpg']);
        $conversation->refresh();

        $this->assertSame('collecting', $conversation->status);
        $this->assertSame(['posts/photo1.jpg'], $conversation->images);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('at least 150 words', $lastMessage->message);
    }

    public function test_short_description_without_image_asks_for_image_first(): void
    {
        $this->mockTopicSuggestions();
        [$service, $conversation] = $this->enterSocialPostWorkspace();
        $service->handleMessage($conversation, 'trees', []);
        $conversation->refresh();

        $service->handleMessage($conversation, 'hi', []);
        $conversation->refresh();

        $this->assertSame('collecting', $conversation->status);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('photo', $lastMessage->message);
    }

    public function test_too_short_description_with_image_is_rejected(): void
    {
        $this->mockTopicSuggestions();
        [$service, $conversation] = $this->enterSocialPostWorkspace();
        $service->handleMessage($conversation, 'trees', []);
        $conversation->refresh();

        // 50 words — well short of the 150-word minimum, but not blank,
        // proving the check counts words rather than just checking presence.
        $shortDescription = trim(str_repeat('lovely ', 50));

        $service->handleMessage($conversation, $shortDescription, ['posts/photo1.jpg']);
        $conversation->refresh();

        $this->assertSame('collecting', $conversation->status);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('at least 150 words', $lastMessage->message);
    }

    public function test_valid_description_and_image_curates_with_topic_hint_and_moves_to_preview(): void
    {
        $longDescription = trim(str_repeat('lovely ', 150));

        $this->mock(SocialPostCollectorService::class, function ($mock) {
            $mock->shouldReceive('suggestTopics')
                ->once()
                ->andReturn(['Food', 'Travel', 'Nature', 'Pets', 'Fitness', 'Art']);

            $mock->shouldReceive('acknowledge')
                ->once()
                ->with('Trees', 'A beautiful tree in a scenic setting.')
                ->andReturn('Got it — putting your tree post together now!');
        });

        $this->mock(PostCuratorService::class, function ($mock) use ($longDescription) {
            $mock->shouldReceive('curate')
                ->once()
                ->with($longDescription, ['posts/photo1.jpg'], 'trees')
                ->andReturn([
                    'topic'             => 'Trees',
                    'description'       => 'A majestic tree stands tall, its branches reaching for the sky.',
                    'short_description' => 'A beautiful tree in a scenic setting.',
                    'tags'              => ['trees', 'nature', 'outdoors'],
                ]);
        });

        [$service, $conversation] = $this->enterSocialPostWorkspace();
        $service->handleMessage($conversation, 'trees', []);
        $conversation->refresh();

        $service->handleMessage($conversation, $longDescription, ['posts/photo1.jpg']);
        $conversation->refresh();

        $this->assertSame('preview', $conversation->status);
        $this->assertSame('Trees', $conversation->topic);
        $this->assertSame(['trees', 'nature', 'outdoors'], $conversation->tags);

        $preview = $conversation->messages()->where('type', 'post')->get()->last();
        $this->assertNotNull($preview);

        $pills = $conversation->messages()->where('type', 'pills')->get()->last();
        $this->assertSame(['Approve posting to the feed', 'Edit post', 'Delete post'], json_decode($pills->message, true));
    }
}
