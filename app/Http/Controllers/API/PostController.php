<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\ReportPostRequest;
use App\Http\Requests\Post\SharePostRequest;
use App\Http\Resources\PostResource;
use App\Models\FeedCategory;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostReport;
use App\Models\PostShare;
use App\Models\UserBlock;
use App\Models\UserConnection;
use App\Models\UserFeedTopic;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class PostController extends Controller
{
    use ApiResponse;

    /**
     * Paginated feed with full filtering support.
     *
     * Query params:
     *   category   newest|local|friendship|trending|olympics  (default: newest)
     *   topic_id   integer  — user's own custom topic (overrides category)
     *   type       all|user|ai                               (default: all)
     *   post_type  all|social|event|ad                       (default: all)
     *   per_page   1-50                                      (default: 15)
     */
    public function feed(Request $request)
    {
        $userId   = Auth::guard('api')->id();
        $user     = \App\Models\User::find($userId);

        $category = $request->query('category', 'newest');
        $topicId  = $request->query('topic_id');
        $type     = $request->query('type', 'all');
        $postType = $request->query('post_type', 'all');
        $perPage  = min(max((int) $request->query('per_page', 15), 1), 50);

        // IDs of users blocked in either direction
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
            ->with(['user', 'images', 'workspace'])
            ->withCount(['likes', 'shares'])
            ->withExists(['likes as is_liked' => fn ($q) => $q->where('user_id', $userId)])
            ->whereNotIn('user_id', $blockedIds);

        // ── created_by filter (type param) ────────────────────────────
        if ($type === 'user') {
            $query->where('created_by', 'user');
        } elseif ($type === 'ai') {
            $query->where('created_by', 'ai');
        }

        // ── post type filter ──────────────────────────────────────────
        if ($postType === 'social') {
            $query->where('type', 'regular');
        } elseif ($postType === 'event') {
            $query->where('type', 'event');
        } elseif ($postType === 'ad') {
            $query->where('type', 'ad');
        }

        // ── category / topic filter ───────────────────────────────────
        if ($topicId) {
            $topic = UserFeedTopic::where('id', $topicId)->where('user_id', $userId)->first();

            if (!$topic) {
                return $this->error([], 'Topic not found', 404);
            }

            $topicName = mb_strtolower($topic->name);

            $query->where('visibility', 'public')
                ->where(function ($q) use ($topicName) {
                    $q->whereJsonContains('tags', $topicName)
                      ->orWhere('topic', 'like', "%{$topicName}%");
                })
                ->latest('published_at');
        } else {
            switch ($category) {
                case 'local':
                    if (blank($user?->latitude) || blank($user?->longitude)) {
                        return $this->error(
                            [],
                            'Location not set. Please update your location to use the Local feed.',
                            422
                        );
                    }

                    $lat    = (float) $user->latitude;
                    $lng    = (float) $user->longitude;
                    $radius = 50; // km

                    $nearbyIds = DB::table('users')
                        ->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->whereRaw(
                            '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?',
                            [$lat, $lng, $lat, $radius]
                        )
                        ->pluck('id')
                        ->all();

                    $query->whereIn('user_id', $nearbyIds)
                        ->where('visibility', 'public')
                        ->latest('published_at');
                    break;

                case 'friendship':
                    $friendIds = UserConnection::where('user_one_id', $userId)
                        ->orWhere('user_two_id', $userId)
                        ->get()
                        ->flatMap(fn ($c) => [$c->user_one_id, $c->user_two_id])
                        ->filter(fn ($id) => $id !== $userId)
                        ->unique()
                        ->values()
                        ->all();

                    $query->whereIn('user_id', $friendIds)
                        ->where(function ($q) {
                            $q->where('visibility', 'public')
                              ->orWhere('visibility', 'friends');
                        })
                        ->latest('published_at');
                    break;

                case 'trending':
                    $query->where('visibility', 'public')
                        ->where('published_at', '>=', now()->subDays(7))
                        ->orderByDesc('likes_count')
                        ->latest('published_at');
                    break;

                case 'olympics':
                    $feedCategory = FeedCategory::where('slug', 'olympics')->first();
                    $keywords     = $feedCategory?->tag_keywords
                        ?? ['olympics', 'olympic', 'athlete', 'medal', 'sports', 'games'];

                    $query->where('visibility', 'public')
                        ->where(function ($q) use ($keywords) {
                            foreach ($keywords as $keyword) {
                                $q->orWhereJsonContains('tags', mb_strtolower($keyword));
                            }
                        })
                        ->latest('published_at');
                    break;

                case 'newest':
                default:
                    $query->where('visibility', 'public')
                        ->latest('published_at');
                    break;
            }
        }

        $posts = $query->paginate($perPage);

        return $this->success([
            'posts' => PostResource::collection($posts->items()),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'per_page'     => $posts->perPage(),
                'total'        => $posts->total(),
                'last_page'    => $posts->lastPage(),
            ],
        ], $posts->isEmpty() ? 'No posts found' : 'Feed fetched successfully');
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
        $like   = PostLike::where('post_id', $post->id)->where('user_id', $userId)->first();

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

        $userId   = Auth::guard('api')->id();
        $platform = $request->validated()['platform'];

        PostShare::create([
            'post_id'  => $post->id,
            'user_id'  => $userId,
            'platform' => $platform,
        ]);

        $postUrl = url('/api/posts/' . $post->id);

        $shareUrl = match ($platform) {
            'facebook'  => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($postUrl),
            'twitter'   => 'https://twitter.com/intent/tweet?url=' . urlencode($postUrl),
            'instagram' => 'https://www.instagram.com/?url=' . urlencode($postUrl),
            'linkedin'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($postUrl),
            'whatsapp'  => 'https://wa.me/?text=' . urlencode($postUrl),
            'telegram'  => 'https://t.me/share/url?url=' . urlencode($postUrl),
            default     => $postUrl,
        };

        return $this->success(['share_url' => $shareUrl], 'Share recorded');
    }

    /**
     * Report a post. A user may report the same post multiple times with different reasons.
     */
    public function report(ReportPostRequest $request, int $id)
    {
        $post = Post::find($id);

        if (!$post) {
            return $this->error([], 'Post not found', 404);
        }

        $userId = Auth::guard('api')->id();

        // Prevent reporting your own post
        if ($post->user_id === $userId) {
            return $this->error([], 'You cannot report your own post', 422);
        }

        PostReport::create([
            'post_id'     => $post->id,
            'user_id'     => $userId,
            'reason'      => $request->validated()['reason'],
            'description' => $request->validated()['description'] ?? null,
            'status'      => 'pending',
        ]);

        return $this->success([], 'Post reported successfully');
    }

    /**
     * Delete the authenticated user's own post.
     */
    public function destroy(int $id)
    {
        $userId = Auth::guard('api')->id();

        $post = Post::where('id', $id)->where('user_id', $userId)->first();

        if (!$post) {
            return $this->error([], 'Post not found or you do not have permission to delete it', 404);
        }

        $post->delete();

        return $this->success([], 'Post deleted successfully');
    }
}
