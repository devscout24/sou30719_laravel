<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use Illuminate\Support\Facades\Log;

class SocialPostCollectorService
{
    protected const FALLBACK_ACK_REPLY = "Got it! Here's what I put together:";

    public function __construct(protected OpenAIService $openai)
    {
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
}
