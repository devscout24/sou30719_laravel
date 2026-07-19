<?php

namespace App\Services;

use App\Models\Post;
use App\Models\UserBlock;
use App\Services\AI\FeedSearchIntentClassifierService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FeedSearchService
{
    public function __construct(
        protected FeedSearchIntentClassifierService $intentClassifier,
    ) {
    }

    /**
     * Conversational entry point: classify the message as small talk, an unclear
     * request, or a concrete search, and only run the feed query for the latter.
     *
     * @param  array<int, array{role: string, content: string}>  $history  prior turns, oldest first
     * @return array{intent: string, reply: string, posts: ?LengthAwarePaginator}
     */
    public function chat(string $message, int $userId, array $history = [], int $perPage = 15): array
    {
        $classification = $this->intentClassifier->classify($message, $history);

        $posts = $classification['intent'] === 'search'
            ? $this->queryPosts($classification['keywords'], $userId, $perPage)
            : null;

        return [
            'intent' => $classification['intent'],
            'reply'  => $classification['reply'],
            'posts'  => $posts,
        ];
    }

    /**
     * @param  string[]  $keywords
     */
    protected function queryPosts(array $keywords, int $userId, int $perPage): LengthAwarePaginator
    {
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
}
