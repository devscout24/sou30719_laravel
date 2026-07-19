<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use Illuminate\Support\Facades\Log;

class FeedSearchIntentClassifierService
{
    protected const FALLBACK_REPLY = "I couldn't quite understand that. Could you tell me what you're looking for on the feed?";

    public function __construct(protected OpenAIService $openai)
    {
    }

    /**
     * Classify a chat message as small talk, an unclear request, or a concrete
     * feed search — and produce a natural conversational reply either way.
     *
     * @param  array<int, array{role: string, content: string}>  $history  prior turns, oldest first
     * @return array{intent: string, reply: string, keywords: string[]}
     */
    public function classify(string $message, array $history = []): array
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt()]],
            $history,
            [['role' => 'user', 'content' => $message]],
        );

        try {
            $content = $this->openai->chat($messages, jsonMode: true);
        } catch (AIServiceException $e) {
            Log::warning('Feed search intent classification failed', ['error' => $e->getMessage()]);

            // Keep the feature usable when AI is down: treat the raw words as a
            // best-effort search rather than dead-ending the conversation.
            return [
                'intent'   => 'search',
                'reply'    => "Here's what I found:",
                'keywords' => array_values(array_filter(array_map('trim', explode(' ', $message)))),
            ];
        }

        $decoded = json_decode($content, true);
        $intent  = $decoded['intent'] ?? 'unclear';

        if (!in_array($intent, ['greeting', 'unclear', 'search'], true)) {
            $intent = 'unclear';
        }

        $reply = trim((string) ($decoded['reply'] ?? '')) ?: self::FALLBACK_REPLY;

        $keywords = $intent === 'search'
            ? array_values(array_filter(array_map('trim', (array) ($decoded['keywords'] ?? []))))
            : [];

        return ['intent' => $intent, 'reply' => $reply, 'keywords' => $keywords];
    }

    protected function systemPrompt(): string
    {
        return <<<'TEXT'
            You are a friendly assistant embedded in the feed search of a social app. You chat with the
            user turn by turn to help them find posts in the feed.

            Classify the user's latest message into exactly one intent:
            - "greeting": small talk, greetings, thanks, or chit-chat with no search request (e.g. "hi",
              "how are you", "thanks a lot").
            - "unclear": the user seems to want something but it's too vague to search with (e.g. "show me
              stuff", "posts", "something interesting").
            - "search": the user clearly describes something to find in the feed — a topic, activity,
              event, product, place, or content theme.

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {"intent": "greeting" | "unclear" | "search", "reply": "<natural 1-2 sentence reply>", "keywords": ["keyword1", "keyword2"]}

            Rules:
            - If intent is "greeting", reply warmly and ask what they're looking for on the feed. keywords must be [].
            - If intent is "unclear", ask the user to be more specific about what they'd like to find. keywords must be [].
            - If intent is "search", write reply as a short, natural intro (e.g. "Here's what I found for you!"),
              and set keywords to 3-10 meaningful lowercase keywords or short phrases (nouns, topics,
              activities, places, content themes), removing filler words like "show me" or "I want to see".
            - Use the conversation history for context — e.g. if the user is answering a clarifying question
              you just asked, combine it with earlier turns to decide the intent.
            TEXT;
    }
}
