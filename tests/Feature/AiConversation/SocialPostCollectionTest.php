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

    protected function startSocialPostConversation(): AiConversation
    {
        Workspace::create([
            'title'        => 'Social Post',
            'description'  => 'Share a post.',
            'prompt'       => 'Share a post',
            'slug'         => Workspace::SLUG_SOCIAL_POST,
            'is_supported' => true,
            'status'       => 'active',
            'sort_order'   => 1,
        ]);
        $user = User::factory()->create();

        $service = app(WorkspaceConversationService::class);
        $started = $service->startConversation($user->id);
        $conversation = AiConversation::find($started['conversation_id']);

        $service->handleMessage($conversation, 'Share a post', []);

        return $conversation->refresh();
    }

    public function test_image_without_description_asks_for_description(): void
    {
        $conversation = $this->startSocialPostConversation();
        $service = app(WorkspaceConversationService::class);

        $service->handleMessage($conversation, null, ['posts/photo1.jpg']);
        $conversation->refresh();

        $this->assertSame('collecting', $conversation->status);
        $this->assertSame(['posts/photo1.jpg'], $conversation->images);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('bit more detail', $lastMessage->message);
    }

    public function test_short_description_without_image_asks_for_image_first(): void
    {
        $conversation = $this->startSocialPostConversation();
        $service = app(WorkspaceConversationService::class);

        $service->handleMessage($conversation, 'hi', []);
        $conversation->refresh();

        $this->assertSame('collecting', $conversation->status);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('photo', $lastMessage->message);
    }

    public function test_too_short_description_with_image_is_rejected(): void
    {
        $conversation = $this->startSocialPostConversation();
        $service = app(WorkspaceConversationService::class);

        $service->handleMessage($conversation, 'hi there', ['posts/photo1.jpg']);
        $conversation->refresh();

        $this->assertSame('collecting', $conversation->status);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('bit more detail', $lastMessage->message);
    }

    public function test_valid_description_and_image_curates_and_moves_to_preview(): void
    {
        $conversation = $this->startSocialPostConversation();

        $this->mock(PostCuratorService::class, function ($mock) {
            $mock->shouldReceive('curate')
                ->once()
                ->with('A lovely sunset over the bay', ['posts/photo1.jpg'])
                ->andReturn([
                    'topic'             => 'Sunset',
                    'description'       => 'A lovely sunset over the bay, painted gold and pink.',
                    'short_description' => 'A gorgeous sunset over the bay.',
                    'tags'              => ['sunset', 'bay', 'evening'],
                ]);
        });

        // finalizePost() also calls SocialPostCollectorService::acknowledge(),
        // which otherwise makes a real OpenAI HTTP call — mock it too.
        $this->mock(SocialPostCollectorService::class, function ($mock) {
            $mock->shouldReceive('acknowledge')
                ->once()
                ->with('Sunset', 'A gorgeous sunset over the bay.')
                ->andReturn('Got it — putting your sunset post together now!');
        });

        $service = app(WorkspaceConversationService::class);
        $service->handleMessage($conversation, 'A lovely sunset over the bay', ['posts/photo1.jpg']);
        $conversation->refresh();

        $this->assertSame('preview', $conversation->status);
        $this->assertSame('Sunset', $conversation->topic);
        $this->assertSame(['sunset', 'bay', 'evening'], $conversation->tags);

        $preview = $conversation->messages()->where('type', 'post')->get()->last();
        $this->assertNotNull($preview);

        $pills = $conversation->messages()->where('type', 'pills')->get()->last();
        $this->assertSame(['Approve posting to the feed', 'Edit post', 'Delete post'], json_decode($pills->message, true));
    }
}
