<?php

namespace App\Services;

use App\Exceptions\AIServiceException;
use App\Models\Post;
use App\Models\UserBlock;
use App\Services\AI\OpenAIService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FeedSearchService
{
    public function __construct(protected OpenAIService $openAI)
    {
    }

    /**
     * Extract keywords from the user's natural-language prompt using AI,
     * then search posts by those keywords across tags, topic, title, and descriptions.
     */
    public function search(string $prompt, int $userId, int $perPage = 15): LengthAwarePaginator
    {
        $keywords = $this->extractKeywords($prompt);

        $blockedIds = UserBlock::where('user_id', $userId)
            ->orWhere('blocked_user_id', $userId)
            ->get()
            ->flatMap(fn ($b) => [$b->user_id, $b->blocked_user_id])
            ->filter(fn ($id) => $id !== $userId)
            ->unique()
            ->values()
            ->all();

        $query = Post::query()
            ->published()
            ->where('visibility', 'public')
            ->whereNotIn('user_id', $blockedIds)
            ->with(['user', 'images', 'workspace'])
            ->withCount(['likes', 'shares'])
            ->withExists(['likes as is_liked' => fn ($q) => $q->where('user_id', $userId)]);

        if (!empty($keywords)) {
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $kw = mb_strtolower(trim($keyword));
                    if (blank($kw)) {
                        continue;
                    }
                    $q->orWhereJsonContains('tags', $kw)
                      ->orWhere('topic', 'like', "%{$kw}%")
                      ->orWhere('title', 'like', "%{$kw}%")
                      ->orWhere('short_description', 'like', "%{$kw}%")
                      ->orWhere('content', 'like', "%{$kw}%");
                }
            });
        }

        return $query->latest('published_at')->paginate($perPage);
    }

    /**
     * @return string[]
     */
    protected function extractKeywords(string $prompt): array
    {
        try {
            $reply = $this->openAI->chat([
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ], jsonMode: true);

            $decoded = json_decode($reply, true);

            return array_values(array_filter(
                array_map('trim', (array) ($decoded['keywords'] ?? []))
            ));
        } catch (AIServiceException) {
            // Fall back to a simple tokenisation so search still works
            return array_values(array_filter(
                array_map('trim', explode(' ', $prompt))
            ));
        }
    }

    protected function systemPrompt(): string
    {
        return <<<'TEXT'
            You are a search-keyword extractor for a social media platform.
            Given a user's natural-language search query, extract the most relevant search keywords or short phrases.

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {"keywords": ["keyword1", "keyword2", "keyword3"]}

            Rules:
            - Extract 3-10 meaningful lowercase keywords or short phrases.
            - Focus on nouns, topics, activities, places, or content themes.
            - Remove filler words (e.g. "show me", "I want to see", "find posts about").
            TEXT;
    }
}
