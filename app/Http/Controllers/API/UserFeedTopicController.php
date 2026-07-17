<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\StoreTopicRequest;
use App\Http\Resources\UserFeedTopicResource;
use App\Models\UserFeedTopic;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class UserFeedTopicController extends Controller
{
    use ApiResponse;

    protected const MAX_CUSTOM_TOPICS = 5;

    /**
     * The unified feed-topics list: the fixed (built-in) topics every user shares,
     * followed by this user's own custom topics. Also served at /feed/categories
     * for backward compatibility with older clients.
     */
    public function index()
    {
        $userId = Auth::guard('api')->id();

        $topics = UserFeedTopic::where('is_active', true)
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->orderByDesc('is_fixed')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return $this->success(
            UserFeedTopicResource::collection($topics),
            'Topics fetched successfully'
        );
    }

    /**
     * Add a new custom topic (max 5 per user, on top of the fixed topics).
     */
    public function store(StoreTopicRequest $request)
    {
        $userId = Auth::guard('api')->id();

        $count = UserFeedTopic::where('user_id', $userId)->count();

        if ($count >= self::MAX_CUSTOM_TOPICS) {
            return $this->error(
                [],
                'You have reached the maximum of ' . self::MAX_CUSTOM_TOPICS . ' custom topics.',
                422
            );
        }

        $name = trim($request->validated()['name']);
        $normalized = mb_strtolower($name);

        // Case-insensitive duplicate check against both the fixed topics and this user's own
        $exists = UserFeedTopic::where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->exists();

        if ($exists) {
            return $this->error([], 'A topic with this name already exists.', 422);
        }

        $topic = UserFeedTopic::create([
            'user_id'  => $userId,
            'name'     => $name,
            'is_fixed' => false,
        ]);

        return $this->success(
            new UserFeedTopicResource($topic),
            'Topic added successfully'
        );
    }

    /**
     * Remove one of the authenticated user's custom topics.
     * Fixed topics have no user_id, so they can never match here.
     */
    public function destroy(int $id)
    {
        $userId = Auth::guard('api')->id();

        $topic = UserFeedTopic::where('id', $id)->where('user_id', $userId)->first();

        if (!$topic) {
            return $this->error([], 'Topic not found', 404);
        }

        $topic->delete();

        return $this->success([], 'Topic removed successfully');
    }
}
