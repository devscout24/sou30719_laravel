<?php

namespace App\Http\Controllers\API;

use App\Exceptions\AIServiceException;
use App\Http\Controllers\Controller;
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
}
