<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserBlock;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class BlockController extends Controller
{
    use ApiResponse;

    /**
     * Block a user. Blocks are one-directional; feed filtering covers both directions.
     */
    public function block(int $userId)
    {
        $currentUserId = Auth::guard('api')->id();

        if ($currentUserId === $userId) {
            return $this->error([], 'You cannot block yourself', 422);
        }

        $exists = UserBlock::where('user_id', $currentUserId)
            ->where('blocked_user_id', $userId)
            ->exists();

        if ($exists) {
            return $this->error([], 'User is already blocked', 422);
        }

        UserBlock::create([
            'user_id'         => $currentUserId,
            'blocked_user_id' => $userId,
        ]);

        return $this->success([], 'User blocked successfully');
    }

    /**
     * Unblock a previously blocked user.
     */
    public function unblock(int $userId)
    {
        $currentUserId = Auth::guard('api')->id();

        $block = UserBlock::where('user_id', $currentUserId)
            ->where('blocked_user_id', $userId)
            ->first();

        if (!$block) {
            return $this->error([], 'This user is not blocked', 404);
        }

        $block->delete();

        return $this->success([], 'User unblocked successfully');
    }
}
