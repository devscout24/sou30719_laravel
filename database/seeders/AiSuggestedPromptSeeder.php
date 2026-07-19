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
            // creation) screen when no workspace is selected yet — richer, full-sentence
            // examples, distinct from the short Workspace::prompt pill labels used to pick
            // a workspace in the first place.
            ['context' => 'workspace_conversation', 'label' => 'Share a post', 'prompt' => 'I want to share photos from my trip', 'sort_order' => 1],
            ['context' => 'workspace_conversation', 'label' => 'Create an event', 'prompt' => 'Help me create an event for this Saturday', 'sort_order' => 2, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_EVENT)],
            ['context' => 'workspace_conversation', 'label' => 'Sell something', 'prompt' => 'I want to sell my old bicycle', 'sort_order' => 3, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_MARKET_PLACE)],
            ['context' => 'workspace_conversation', 'label' => 'Find a match', 'prompt' => "I'm looking for someone to match with", 'sort_order' => 4, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_MATCHES)],
            ['context' => 'workspace_conversation', 'label' => 'Request a courier', 'prompt' => 'I need a personal courier for a package', 'sort_order' => 5, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_PERSONAL_COURIER)],

            // Shown once a workspace IS selected (frontend passes ?workspace=<slug>) —
            // several concrete examples scoped to that specific workspace.
            ['context' => 'workspace_conversation', 'label' => 'Post about food', 'prompt' => 'Post about food', 'sort_order' => 10, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_SOCIAL_POST)],
            ['context' => 'workspace_conversation', 'label' => 'Post about the galaxy', 'prompt' => 'Create something about the galaxy', 'sort_order' => 11, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_SOCIAL_POST)],
            ['context' => 'workspace_conversation', 'label' => 'Weekend trip photos', 'prompt' => 'Share photos from my weekend trip', 'sort_order' => 12, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_SOCIAL_POST)],

            ['context' => 'workspace_conversation', 'label' => 'Good height woman', 'prompt' => 'Looking for a woman with good height', 'sort_order' => 10, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_MATCHES)],
            ['context' => 'workspace_conversation', 'label' => 'Someone who loves hiking', 'prompt' => 'Find someone who loves hiking', 'sort_order' => 11, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_MATCHES)],
            ['context' => 'workspace_conversation', 'label' => 'Match my preferences', 'prompt' => 'Match me based on my dating preferences', 'sort_order' => 12, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_MATCHES)],

            ['context' => 'workspace_conversation', 'label' => 'List my phone for sale', 'prompt' => 'List my phone for sale', 'sort_order' => 11, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_MARKET_PLACE)],
            ['context' => 'workspace_conversation', 'label' => 'Offer a discount', 'prompt' => 'Offer a discount on my service', 'sort_order' => 12, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_MARKET_PLACE)],

            ['context' => 'workspace_conversation', 'label' => 'Plan a birthday party', 'prompt' => 'Plan a birthday party event', 'sort_order' => 10, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_EVENT)],
            ['context' => 'workspace_conversation', 'label' => 'Weekend meetup', 'prompt' => 'Create an event for a weekend meetup', 'sort_order' => 11, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_EVENT)],

            ['context' => 'workspace_conversation', 'label' => 'Deliver a gift', 'prompt' => 'Request delivery for a gift', 'sort_order' => 10, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_PERSONAL_COURIER)],
            ['context' => 'workspace_conversation', 'label' => 'Pick up a package', 'prompt' => 'I need someone to pick up a package for me', 'sort_order' => 11, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_PERSONAL_COURIER)],

            ['context' => 'workspace_conversation', 'label' => 'Suggest a hobby group', 'prompt' => 'Suggest a hobby group for me', 'sort_order' => 10, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_INTEREST_HUB)],
            ['context' => 'workspace_conversation', 'label' => 'Photography lovers', 'prompt' => 'I want to connect with people who love photography', 'sort_order' => 11, 'workspace_id' => $workspaceIdFor(Workspace::SLUG_INTEREST_HUB)],
        ];

        foreach ($prompts as $prompt) {
            AiSuggestedPrompt::updateOrCreate(
                ['context' => $prompt['context'], 'prompt' => $prompt['prompt']],
                $prompt
            );
        }
    }
}
