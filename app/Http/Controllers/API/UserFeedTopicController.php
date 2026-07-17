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

    protected const MAX_TOPICS = 10;

    /**
     * List the authenticated user's custom feed topics.
     */
    public function index()
    {
        $userId = Auth::guard('api')->id();

        $topics = UserFeedTopic::where('user_id', $userId)
            ->orderBy('created_at')
            ->get();

        return $this->success(
            UserFeedTopicResource::collection($topics),
            'Topics fetched successfully'
        );
    }

    /**
     * Add a new custom topic (max 10 per user).
     */
    public function store(StoreTopicRequest $request)
    {
        $userId = Auth::guard('api')->id();

        $count = UserFeedTopic::where('user_id', $userId)->count();

        if ($count >= self::MAX_TOPICS) {
            return $this->error(
                [],
                'You have reached the maximum of ' . self::MAX_TOPICS . ' custom topics.',
                422
            );
        }

        $name = trim($request->validated()['name']);

        // Case-insensitive duplicate check
        $exists = UserFeedTopic::where('user_id', $userId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            return $this->error([], 'You already have a topic with this name.', 422);
        }

        $topic = UserFeedTopic::create([
            'user_id' => $userId,
            'name'    => $name,
        ]);

        return $this->success(
            new UserFeedTopicResource($topic),
            'Topic added successfully'
        );
    }

    /**
     * Remove one of the authenticated user's custom topics.
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
