<?php

namespace App\Http\Controllers\API;

use App\Exceptions\AIServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateAiPostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\AI\PostCuratorService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class AdminAiPostController extends Controller
{
    use ApiResponse;

    public function __construct(protected PostCuratorService $curator)
    {
    }

    /**
     * Generate and publish an AI-authored post.
     * Only accessible by admins. The post is immediately published and
     * will appear as an AI-Pal post in all users' feeds.
     */
    public function store(GenerateAiPostRequest $request)
    {
        $data = $request->validated();

        try {
            $result = $this->curator->generateAdminPost($data['theme']);
        } catch (AIServiceException $e) {
            return $this->error([], $e->getMessage(), $e->getCode() ?: 502);
        }

        $post = Post::create([
            'user_id'           => Auth::guard('api')->id(),
            'topic'             => $result['topic'],
            'type'              => $data['post_type'] ?? 'regular',
            'created_by'        => 'ai',
            'content'           => $result['description'],
            'short_description' => $result['short_description'],
            'tags'              => $result['tags'],
            'visibility'        => $data['visibility'] ?? 'public',
            'status'            => 'published',
            'published_at'      => now(),
        ]);

        // Reload relationships for the resource
        $post->load(['user', 'images', 'workspace']);
        $post->loadCount(['likes', 'shares']);

        return $this->success(
            new PostResource($post),
            'AI post generated and published successfully'
        );
    }
}
