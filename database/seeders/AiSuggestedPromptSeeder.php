<?php

namespace Database\Seeders;

use App\Models\AiSuggestedPrompt;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class AiSuggestedPromptSeeder extends Seeder
{
    public function run(): void
    {
        $workspaceIdFor = fn (string $slug) => Workspace::where('slug', $slug)->value('id');

        $prompts = [
            // Shown before the user types anything on the AI Feed Search screen
            // (POST /feed/ai-chat).
            ['context' => 'feed_search', 'label' => 'Hiking trips', 'prompt' => 'Find posts about hiking trips', 'sort_order' => 1],
            ['context' => 'feed_search', 'label' => 'Weekend events', 'prompt' => "Show me events happening this weekend", 'sort_order' => 2],
            ['context' => 'feed_search', 'label' => 'Marketplace deals', 'prompt' => 'Any good deals on the marketplace?', 'sort_order' => 3],
            ['context' => 'feed_search', 'label' => 'Trending near me', 'prompt' => "What's trending in my area right now?", 'sort_order' => 4],
            ['context' => 'feed_search', 'label' => 'Home decor', 'prompt' => 'Posts about home decor ideas', 'sort_order' => 5],

            // Shown before the user's first message on the AI Conversation (post/ad/event
            // creation) screen — richer, full-sentence examples, distinct from the short
            // Workspace::prompt pill labels used mid-conversation.
            ['context' => 'workspace_conversation', 'label' => 'Share a post', 'prompt' => 'I want to share photos from my trip', 'sort_order' => 1],
            ['context' => 'workspace_conversation', 'label' => 'Create an event', 'prompt' => 'Help me create an event for this Saturday', 'sort_order' => 2, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_EVENT)],
            ['context' => 'workspace_conversation', 'label' => 'Sell something', 'prompt' => 'I want to sell my old bicycle', 'sort_order' => 3, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_MARKET_PLACE)],
            ['context' => 'workspace_conversation', 'label' => 'Find a match', 'prompt' => "I'm looking for someone to match with", 'sort_order' => 4, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_MATCHES)],
            ['context' => 'workspace_conversation', 'label' => 'Request a courier', 'prompt' => 'I need a personal courier for a package', 'sort_order' => 5, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_PERSONAL_COURIER)],
        ];

        foreach ($prompts as $prompt) {
            AiSuggestedPrompt::updateOrCreate(
                ['context' => $prompt['context'], 'prompt' => $prompt['prompt']],
                $prompt
            );
        }
    }
}
