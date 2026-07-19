<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use Illuminate\Support\Facades\Log;

class SocialPostCollectorService
{
    protected const FALLBACK_TOPIC_REPLY = "I didn't quite catch what you'd like to post about — could you tell me in a few words?";
    protected const FALLBACK_DETAILS_REPLY = 'Great! Want to add any details? And please share a photo to go with it.';
    protected const FALLBACK_ACK_REPLY = "Got it! Here's what I put together:";

    public function __construct(protected OpenAIService $openai)
    {
    }

    /**
     * Phase 1: decide whether a message clearly states a postable topic.
     *
     * @param  array<int, array{role: string, content: string}>  $history  prior turns, oldest first
     * @return array{topic: ?string, reply: ?string} reply is only meaningful when topic is null
     */
    public function classifyTopic(string $message, array $history = []): array
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->topicSystemPrompt()]],
            $history,
            [['role' => 'user', 'content' => $message]],
        );

        try {
            $content = $this->openai->chat($messages, jsonMode: true);
        } catch (AIServiceException $e) {
            Log::warning('Social post topic classification failed', ['error' => $e->getMessage()]);

            return ['topic' => null, 'reply' => self::FALLBACK_TOPIC_REPLY];
        }

        $decoded = json_decode($content, true);
        $topic   = trim((string) ($decoded['topic'] ?? ''));
        $reply   = trim((string) ($decoded['reply'] ?? ''));

        return [
            'topic' => $topic !== '' ? $topic : null,
            'reply' => $reply !== '' ? $reply : self::FALLBACK_TOPIC_REPLY,
        ];
    }

    /**
     * Phase 2 opener: ask for optional elaboration and a required photo, in the
     * AI's own words, referencing the topic that was just established.
     *
     * @param  array<int, array{role: string, content: string}>  $history  prior turns, oldest first
     */
    public function askForDetails(string $topic, array $history = []): string
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->detailsSystemPrompt($topic)]],
            $history,
        );

        try {
            $content = $this->openai->chat($messages);
        } catch (AIServiceException $e) {
            Log::warning('Social post details prompt failed', ['error' => $e->getMessage()]);

            return self::FALLBACK_DETAILS_REPLY;
        }

        $reply = trim($content);

        return $reply !== '' ? $reply : self::FALLBACK_DETAILS_REPLY;
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

    protected function topicSystemPrompt(): string
    {
        return <<<'TEXT'
            You are a friendly assistant inside the "Social" workspace of a social app, helping a user figure out
            what they'd like to post about. Chat naturally — like a real conversation, not a form.

            Decide whether the user's latest message clearly states a topic or subject for a social media post
            (e.g. "food", "my trip to Bali", "my new puppy"). Vague replies ("hey", "idk", "post", "something")
            do NOT count as a clear topic.

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {"topic": "<short 1-4 word topic, or empty string if unclear>", "reply": "<natural 1-2 sentence reply, only used if topic is unclear>"}

            Rules:
            - If a clear topic is present, set "topic" to a short label and "reply" can be empty.
            - If unclear, set "topic" to an empty string and "reply" to a warm, natural follow-up question asking
              what they'd like to post about — vary your wording, don't repeat a question you've already asked in
              this conversation.
            TEXT;
    }

    protected function detailsSystemPrompt(string $topic): string
    {
        return <<<TEXT
            You are a friendly assistant inside the "Social" workspace of a social app. The user just told you
            they want to post about: "{$topic}".

            Reply with ONE short, warm, natural message (1-2 sentences, no markdown) that does two things:
            1. Acknowledges the topic in your own words.
            2. Asks if they'd like to add any details or a description, and asks them to share a photo for the post.

            Do not use a rigid template — vary your phrasing naturally, like a real person chatting.
            TEXT;
    }
}
