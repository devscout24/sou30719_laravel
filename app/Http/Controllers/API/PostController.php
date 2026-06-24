<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\SharePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostShare;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class PostController extends Controller
{
    use ApiResponse;

    /**
     * Toggle a like on a post.
     */
    public function like(int $id)
    {
        $post = Post::find($id);

        if (!$post) {
            return $this->error([], 'Post not found', 404);
        }

        $userId = Auth::guard('api')->id();
        $like = PostLike::where('post_id', $post->id)->where('user_id', $userId)->first();

        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            try {
                PostLike::create(['post_id' => $post->id, 'user_id' => $userId]);
            } catch (Throwable) {
                // Unique constraint hit by a concurrent request — already liked.
            }
            $liked = true;
        }

        return $this->success(['liked' => $liked], 'Like toggled');
    }

    /**
     * Record a share for a post and return a platform share link.
     */
    public function share(SharePostRequest $request, int $id)
    {
        $post = Post::find($id);

        if (!$post) {
            return $this->error([], 'Post not found', 404);
        }

        $userId = Auth::guard('api')->id();
        $platform = $request->validated()['platform'];

        PostShare::create([
            'post_id' => $post->id,
            'user_id' => $userId,
            'platform' => $platform,
        ]);

        $postUrl = url('/api/posts/' . $post->id);

        $shareUrl = match ($platform) {
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($postUrl),
            'twitter' => 'https://twitter.com/intent/tweet?url=' . urlencode($postUrl),
            'instagram' => 'https://www.instagram.com/?url=' . urlencode($postUrl),
            'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($postUrl),
            'whatsapp' => 'https://wa.me/?text=' . urlencode($postUrl),
            'telegram' => 'https://t.me/share/url?url=' . urlencode($postUrl),
            default => $postUrl,
        };

        return $this->success(['share_url' => $shareUrl], 'Share recorded');
    }

    /**
     * Paginated feed of published posts.
     */
    public function feed(Request $request)
    {
        $userId = Auth::guard('api')->id();

        $perPage = (int) $request->query('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 50) : 15;

        $posts = Post::query()
            ->published()
            ->visible()
            ->with(['user', 'images', 'workspace'])
            ->withCount(['likes', 'shares'])
            ->withExists(['likes as is_liked' => fn ($q) => $q->where('user_id', $userId)])
            ->latest('published_at')
            ->paginate($perPage);

        return $this->success([
            'posts' => PostResource::collection($posts->items()),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'last_page' => $posts->lastPage(),
            ],
        ], 'Feed fetched successfully');
    }

    /**
     * Single post details.
     */
    public function show(int $id)
    {
        $userId = Auth::guard('api')->id();

        $post = Post::query()
            ->with(['user', 'images', 'workspace'])
            ->withCount(['likes', 'shares'])
            ->withExists(['likes as is_liked' => fn ($q) => $q->where('user_id', $userId)])
            ->find($id);

        if (!$post) {
            return $this->error([], 'Post not found', 404);
        }

        return $this->success(new PostResource($post), 'Post fetched successfully');
    }
}
