<?php

namespace Tests\Feature\AiConversation;

use App\Models\AiConversation;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\PostCuratorService;
use App\Services\WorkspaceConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function makeMarketplaceWorkspace(): Workspace
    {
        return Workspace::create([
            'title'        => 'Market Place',
            'description'  => 'Sell something.',
            'prompt'       => 'Sell something',
            'slug'         => Workspace::SLUG_MARKET_PLACE,
            'is_supported' => true,
            'status'       => 'active',
            'sort_order'   => 1,
        ]);
    }

    /**
     * Returns the service plus a fresh conversation already sitting in the
     * Market Place workspace's collecting state.
     */
    protected function enterMarketplaceWorkspace(): array
    {
        $this->makeMarketplaceWorkspace();
        $user = User::factory()->create();

        $service = app(WorkspaceConversationService::class);
        $started = $service->startConversation($user->id);
        $conversation = AiConversation::find($started['conversation_id']);

        $service->handleMessage($conversation, 'Sell something', []);

        return [$service, $conversation->refresh()];
    }

    public function test_entering_workspace_asks_for_ad_form(): void
    {
        [, $conversation] = $this->enterMarketplaceWorkspace();

        $this->assertSame('collecting', $conversation->status);

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertStringContainsString('image, type, category, link, and discount', $lastMessage->message);
    }

    public function test_missing_image_asks_for_image(): void
    {
        [$service, $conversation] = $this->enterMarketplaceWorkspace();

        $service->handleMessage($conversation, null, [], [
            'ad_type'  => 'product',
            'category' => 'electronics',
        ]);
        $conversation->refresh();

        $this->assertSame('collecting', $conversation->status);
        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertSame('Please upload at least one image for your listing.', $lastMessage->message);
    }

    public function test_missing_type_asks_for_type(): void
    {
        [$service, $conversation] = $this->enterMarketplaceWorkspace();

        $service->handleMessage($conversation, null, ['posts/photo1.jpg']);
        $conversation->refresh();

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertSame('Please choose whether this is a product or a service.', $lastMessage->message);
    }

    public function test_missing_category_asks_for_category(): void
    {
        [$service, $conversation] = $this->enterMarketplaceWorkspace();

        $service->handleMessage($conversation, null, ['posts/photo1.jpg'], ['ad_type' => 'product']);
        $conversation->refresh();

        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertSame('Please choose a category for your listing.', $lastMessage->message);
    }

    public function test_missing_description_asks_for_description(): void
    {
        [$service, $conversation] = $this->enterMarketplaceWorkspace();

        $service->handleMessage($conversation, null, ['posts/photo1.jpg'], [
            'ad_type'  => 'product',
            'category' => 'electronics',
        ]);
        $conversation->refresh();

        $this->assertSame('collecting', $conversation->status);
        $lastMessage = $conversation->messages()->where('type', 'message')->get()->last();
        $this->assertSame('Please add a description for your listing.', $lastMessage->message);
    }

    public function test_complete_ad_form_curates_and_moves_to_preview(): void
    {
        $this->mock(PostCuratorService::class, function ($mock) {
            $mock->shouldReceive('curateAd')
                ->once()
                ->with('product', 'electronics', null, null, 'Barely used, great condition.', ['posts/photo1.jpg'])
                ->andReturn([
                    'topic'             => 'iPhone 13 Pro Max',
                    'description'       => 'A well-maintained iPhone 13 Pro Max, barely used and in great condition.',
                    'short_description' => 'Barely used iPhone 13 Pro Max in great condition.',
                    'tags'              => ['electronics', 'phone', 'apple'],
                ]);
        });

        [$service, $conversation] = $this->enterMarketplaceWorkspace();

        $service->handleMessage($conversation, 'Barely used, great condition.', ['posts/photo1.jpg'], [
            'ad_type'  => 'product',
            'category' => 'electronics',
        ]);
        $conversation->refresh();

        $this->assertSame('preview', $conversation->status);
        $this->assertSame('iPhone 13 Pro Max', $conversation->topic);
        $this->assertNull($conversation->csv_path);

        $preview = $conversation->messages()->where('type', 'ad_preview')->get()->last();
        $this->assertNotNull($preview);
        $payload = json_decode($preview->message, true);
        $this->assertNull($payload['csv_file']);

        $pills = $conversation->messages()->where('type', 'pills')->get()->last();
        $this->assertSame(['Approve posting to the feed', 'Edit post', 'Delete post'], json_decode($pills->message, true));
    }

    public function test_ad_form_with_csv_carries_csv_path_through_preview_and_approval(): void
    {
        $this->mock(PostCuratorService::class, function ($mock) {
            $mock->shouldReceive('curateAd')
                ->once()
                ->andReturn([
                    'topic'             => 'Bulk Widgets',
                    'description'       => 'A bulk lot of widgets, prices attached in the sheet.',
                    'short_description' => 'Bulk widgets for sale.',
                    'tags'              => ['widgets', 'bulk'],
                ]);
        });

        [$service, $conversation] = $this->enterMarketplaceWorkspace();

        $service->handleMessage($conversation, 'See attached price sheet.', ['posts/photo1.jpg'], [
            'ad_type'  => 'product',
            'category' => 'other',
            'csv_path' => 'posts/csv/prices.csv',
        ]);
        $conversation->refresh();

        $this->assertSame('preview', $conversation->status);
        $this->assertSame('posts/csv/prices.csv', $conversation->csv_path);

        $preview = $conversation->messages()->where('type', 'ad_preview')->get()->last();
        $payload = json_decode($preview->message, true);
        $this->assertSame('posts/csv/prices.csv', $payload['csv_file']['path']);

        $service->handleMessage($conversation, 'Approve posting to the feed', []);
        $conversation->refresh();

        $this->assertSame('published', $conversation->status);

        $post = Post::find($conversation->post_id);
        $this->assertNotNull($post);
        $this->assertSame('ad', $post->type);
        $this->assertSame('posts/csv/prices.csv', $post->csv_path);
    }
}
