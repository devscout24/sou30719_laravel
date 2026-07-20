<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use Illuminate\Support\Facades\Log;

class SocialPostCollectorService
{
    protected const FALLBACK_ACK_REPLY = "Got it! Here's what I put together:";

    /** @var string[] */
    protected const FALLBACK_TOPIC_PILLS = ['Food', 'Travel', 'Nature', 'Pets', 'Fitness', 'Art'];

    public function __construct(protected OpenAIService $openai)
    {
    }

    /**
     * Six short, varied post-topic suggestions shown as pills when a user
     * enters the Social Post workspace. Freshly generated each time so the
     * options don't feel stale across conversations.
     *
     * @return string[]
     */
    public function suggestTopics(): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->suggestTopicsSystemPrompt()],
        ];

        try {
            $content = $this->openai->chat($messages, jsonMode: true);
        } catch (AIServiceException $e) {
            Log::warning('Social post topic suggestion failed', ['error' => $e->getMessage()]);

            return self::FALLBACK_TOPIC_PILLS;
        }

        $decoded = json_decode($content, true);
        $topics  = array_values(array_filter(array_map('trim', (array) ($decoded['topics'] ?? []))));

        return count($topics) === 6 ? $topics : self::FALLBACK_TOPIC_PILLS;
    }

    /**
     * Short narration shown right before the preview card, so the transition
     * from chat to structured card doesn't feel like a silent robotic dump.
     */
    public function acknowledge(string $topic, string $shortDescription): string
    {
        $messages = [
            [
                'role'    => 'system',
                'content' => 'You are a friendly assistant inside the "Social" workspace of a social app. '
                    . 'The user just finished describing (in words and/or a photo) a post about "' . $topic . '". '
                    . 'Write one short, warm sentence (max 20 words) narrating that you understood it and are '
                    . 'putting the post together now. No markdown, no surrounding quotes, just the sentence itself.',
            ],
            [
                'role'    => 'user',
                'content' => "Short summary of the post: {$shortDescription}",
            ],
        ];

        try {
            $content = $this->openai->chat($messages);
        } catch (AIServiceException $e) {
            Log::warning('Social post acknowledgment failed', ['error' => $e->getMessage()]);

            return self::FALLBACK_ACK_REPLY;
        }

        $reply = trim($content);

        return $reply !== '' ? $reply : self::FALLBACK_ACK_REPLY;
    }

    protected function suggestTopicsSystemPrompt(): string
    {
        return <<<'TEXT'
            You are a friendly assistant inside the "Social" workspace of a social app, helping a user
            decide what to post about. Generate 6 short, varied, everyday post topic ideas (1-2 words
            each, e.g. "Food", "Travel", "Nature", "Pets") that would inspire a wide range of users.
            Mix it up — don't always suggest the same categories.

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {"topics": ["topic1", "topic2", "topic3", "topic4", "topic5", "topic6"]}
            TEXT;
    }
}
