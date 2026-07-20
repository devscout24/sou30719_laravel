<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Matches\StoreMatchTopicRequest;
use App\Http\Resources\MatchTopicResource;
use App\Models\MatchTopic;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class MatchTopicController extends Controller
{
    use ApiResponse;

    protected const MAX_CUSTOM_TOPICS = 5;

    /**
     * Fixed (built-in) tabs plus this user's own custom tabs.
     */
    public function index()
    {
        $userId = Auth::guard('api')->id();

        $topics = MatchTopic::where('is_active', true)
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->orderByDesc('is_fixed')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return $this->success(
            MatchTopicResource::collection($topics),
            'Topics fetched successfully'
        );
    }

    /**
     * Add a new custom tab (max 5 per user, on top of the 6 fixed tabs).
     */
    public function store(StoreMatchTopicRequest $request)
    {
        $userId = Auth::guard('api')->id();

        $count = MatchTopic::where('user_id', $userId)->count();

        if ($count >= self::MAX_CUSTOM_TOPICS) {
            return $this->error(
                [],
                'You have reached the maximum of ' . self::MAX_CUSTOM_TOPICS . ' custom topics.',
                422
            );
        }

        $name = trim($request->validated()['name']);
        $normalized = mb_strtolower($name);

        $exists = MatchTopic::where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->exists();

        if ($exists) {
            return $this->error([], 'A topic with this name already exists.', 422);
        }

        $topic = MatchTopic::create([
            'user_id'  => $userId,
            'name'     => $name,
            'is_fixed' => false,
        ]);

        return $this->success(
            new MatchTopicResource($topic),
            'Topic added successfully'
        );
    }

    /**
     * Remove one of the authenticated user's custom tabs.
     * Fixed tabs have no user_id, so they can never match here.
     */
    public function destroy(int $id)
    {
        $userId = Auth::guard('api')->id();

        $topic = MatchTopic::where('id', $id)->where('user_id', $userId)->first();

        if (!$topic) {
            return $this->error([], 'Topic not found', 404);
        }

        $topic->delete();

        return $this->success([], 'Topic removed successfully');
    }
}
