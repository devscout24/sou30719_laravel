<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\MarketplaceTopic;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBlock;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MarketplaceSearchController extends Controller
{
    use ApiResponse;

    /**
     * Tabbed, filterable browse/search over published marketplace ads.
     *
     * Query params:
     *   topic_id            integer — id from GET /marketplace/topics (fixed or the user's own custom topic)
     *   tab                 string  — legacy-alias fixed-topic slug (no default: omitted means "all ads")
     *   min_price/max_price decimal
     *   max_distance        integer km — requires the caller to have latitude/longitude set
     *   per_page            1-50 (default: 15)
     */
    public function search(Request $request)
    {
        $userId = Auth::guard('api')->id();
        $user   = User::find($userId);

        $topicId  = $request->query('topic_id');
        $tabParam = $request->query('tab');
        $perPage  = min(max((int) $request->query('per_page', 15), 1), 50);

        $topic = $this->resolveMarketplaceTopic($userId, $topicId, $tabParam);

        if (($topicId || $tabParam) && !$topic) {
            return $this->error([], 'Topic not found', 404);
        }

        $minPrice    = $request->query('min_price');
        $maxPrice    = $request->query('max_price');
        $maxDistance = $request->query('max_distance');

        if ($maxDistance !== null && (blank($user?->latitude) || blank($user?->longitude))) {
            return $this->error(
                [],
                'Location not set. Please update your location to use the max_distance filter.',
                422
            );
        }

        $blockedIds = UserBlock::where('user_id', $userId)
            ->orWhere('blocked_user_id', $userId)
            ->get()
            ->flatMap(fn ($b) => [$b->user_id, $b->blocked_user_id])
            ->filter(fn ($id) => $id !== $userId)
            ->unique()
            ->values()
            ->all();

        $query = Post::query()
            ->ads()
            ->published()
            ->visible()
            ->whereNotIn('user_id', $blockedIds)
            ->with(['user', 'images', 'workspace'])
            ->withCount(['likes', 'shares'])
            ->withExists(['likes as is_liked' => fn ($q) => $q->where('user_id', $userId)]);

        if ($minPrice !== null) {
            $query->where('price', '>=', (float) $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        if ($maxDistance !== null) {
            $lat = (float) $user->latitude;
            $lng = (float) $user->longitude;

            $nearbyIds = DB::table('users')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereRaw(
                    '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?',
                    [$lat, $lng, $lat, (float) $maxDistance]
                )
                ->pluck('id')
                ->all();

            $query->whereIn('user_id', $nearbyIds);
        }

        if ($topic) {
            $this->applyTopicFilter($query, $topic);
        }

        $posts = $query->latest('published_at')->paginate($perPage);

        return $this->success([
            'posts' => PostResource::collection($posts->items()),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'per_page'     => $posts->perPage(),
                'total'        => $posts->total(),
                'last_page'    => $posts->lastPage(),
            ],
        ], $posts->isEmpty() ? 'No ads found' : 'Ads fetched successfully');
    }

    /**
     * Resolve the requested topic — by id (fixed or the user's own custom
     * topic), or by the legacy 'tab' slug alias. Returns null when neither is
     * given (the "All Ads" view) or when the given id/slug doesn't resolve.
     */
    protected function resolveMarketplaceTopic(int $userId, ?string $topicId, ?string $tab): ?MarketplaceTopic
    {
        if ($topicId) {
            return MarketplaceTopic::where('id', $topicId)
                ->where('is_active', true)
                ->where(function ($q) use ($userId) {
                    $q->whereNull('user_id')->orWhere('user_id', $userId);
                })
                ->first();
        }

        if ($tab) {
            return MarketplaceTopic::where('slug', $tab)
                ->whereNull('user_id')
                ->where('is_active', true)
                ->first();
        }

        return null;
    }

    /**
     * Apply the resolved topic's filtering to the ad query, in place.
     */
    protected function applyTopicFilter($query, MarketplaceTopic $topic): void
    {
        switch ($topic->slug) {
            case 'products':
                $query->where('ad_type', 'product');
                break;

            case 'services':
                $query->where('ad_type', 'service');
                break;

            case 'cosmetics':
                $this->applyKeywordFilter($query, ['cosmetics', 'beauty', 'skincare', 'makeup']);
                break;

            case 'electronics':
                $this->applyKeywordFilter($query, ['electronics', 'gadget', 'tech']);
                break;

            case 'food':
                $this->applyKeywordFilter($query, ['food', 'grocery', 'restaurant', 'meal']);
                break;

            default:
                // Every custom user topic.
                $keywords = !empty($topic->tag_keywords) ? $topic->tag_keywords : [mb_strtolower($topic->name)];
                $this->applyKeywordFilter($query, $keywords);
                break;
        }
    }

    /**
     * @param  string[]  $keywords
     */
    protected function applyKeywordFilter($query, array $keywords): void
    {
        $query->where(function ($q) use ($keywords) {
            foreach ($keywords as $keyword) {
                $kw = mb_strtolower(trim($keyword));

                if (blank($kw)) {
                    continue;
                }

                $q->orWhere('category', 'like', "%{$kw}%")
                  ->orWhere('title', 'like', "%{$kw}%")
                  ->orWhere('short_description', 'like', "%{$kw}%")
                  ->orWhere('content', 'like', "%{$kw}%")
                  ->orWhereJsonContains('tags', $kw);
            }
        });
    }
}
