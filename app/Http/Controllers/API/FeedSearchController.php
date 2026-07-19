<?php

namespace App\Http\Controllers\API;

use App\Exceptions\AIServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\AiChatRequest;
use App\Http\Requests\Feed\AiSearchRequest;
use App\Http\Resources\PostResource;
use App\Services\FeedSearchService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class FeedSearchController extends Controller
{
    use ApiResponse;

    public function __construct(protected FeedSearchService $searchService)
    {
    }

    /**
     * AI-powered feed search.
     * The user's natural-language prompt is sent to AI which extracts keywords,
     * then matching published public posts are returned.
     */
    public function search(AiSearchRequest $request)
    {
        $userId  = Auth::guard('api')->id();
        $perPage = min((int) ($request->validated()['per_page'] ?? 15), 50);

        try {
            $posts = $this->searchService->search(
                $request->validated()['prompt'],
                $userId,
                $perPage
            );
        } catch (AIServiceException $e) {
            return $this->error([], $e->getMessage(), $e->getCode() ?: 502);
        }

        return $this->success([
            'posts' => PostResource::collection($posts->items()),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'per_page'     => $posts->perPage(),
                'total'        => $posts->total(),
                'last_page'    => $posts->lastPage(),
            ],
        ], $posts->isEmpty() ? 'No matching posts found' : 'Search results fetched successfully');
    }

    /**
     * Conversational AI feed search.
     *
     * The AI decides, per message, whether the user is making small talk, being
     * too vague to search with, or clearly asking for something — and only the
     * "results" type carries posts. `type` is the discriminator the frontend
     * should switch on: "message" (chit-chat), "clarify" (ask to be more
     * specific), or "results" (matching posts attached).
     */
    public function chat(AiChatRequest $request)
    {
        $userId  = Auth::guard('api')->id();
        $data    = $request->validated();
        $perPage = min((int) ($data['per_page'] ?? 15), 50);

        try {
            $result = $this->searchService->chat(
                $data['message'],
                $userId,
                $data['history'] ?? [],
                $perPage
            );
        } catch (AIServiceException $e) {
            return $this->error([], $e->getMessage(), $e->getCode() ?: 502);
        }

        $type = match ($result['intent']) {
            'greeting' => 'message',
            'unclear'  => 'clarify',
            default    => 'results',
        };

        $payload = [
            'type'       => $type,
            'reply'      => $result['reply'],
            'posts'      => null,
            'pagination' => null,
        ];

        if ($type === 'results' && $result['posts']) {
            $posts = $result['posts'];
            $payload['posts'] = PostResource::collection($posts->items());
            $payload['pagination'] = [
                'current_page' => $posts->currentPage(),
                'per_page'     => $posts->perPage(),
                'total'        => $posts->total(),
                'last_page'    => $posts->lastPage(),
            ];
        }

        return $this->success($payload, 'OK');
    }
}
