<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserProfileViewController extends Controller
{
    use ApiResponse;

    /**
     * Shared resolver — finds the target user and blocks access if blocked/not found.
     */
    private function resolveUser(int $id)
    {
        $authUserId = Auth::guard('api')->id();

        $user = User::query()->find($id);

        if (!$user) {
            return null;
        }

        // Don't show profile if either side has blocked the other
        if (\App\Models\UserBlock::isBlocked($authUserId, $id)) {
            return null;
        }

        return $user;
    }

    // ─────────────────────────────────────────────
    // BASIC INFO TAB
    // ─────────────────────────────────────────────

    public function basicInfo($id)
    {
        $user = $this->resolveUser($id);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        return $this->success([
            'name'      => $user->dating_nickname
                ? "{$user->name} ({$user->dating_nickname})"
                : $user->name,
            'avatar'    => asset($user->avatar ?? 'user.png'),
            'location'  => $user->location,
            'bio'       => $user->bio,
            'interests' => $user->interests ?? [],
        ], 'Basic info fetched successfully', 200);
    }

    // ─────────────────────────────────────────────
    // GALLERY TAB
    // ─────────────────────────────────────────────

    public function gallery($id)
    {
        $user = $this->resolveUser($id);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        $images = $user->galleryImages()->get()->map(fn($img) => [
            'id'  => $img->id,
            'url' => $img->full_url,
        ]);

        return $this->success([
            'images' => $images,
        ], 'Gallery fetched successfully', 200);
    }

    // ─────────────────────────────────────────────
    // IDENTITY & LOCATION TAB
    // ─────────────────────────────────────────────

    public function identityLocation($id)
    {
        $user = $this->resolveUser($id);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        // Respect the user's own "connections_view" visibility toggle
        if (!$user->connections_view) {
            return $this->error([], 'This user has hidden their identity & location info.', 403);
        }

        return $this->success([
            'nick_name'    => $user->dating_nickname,
            'status'       => $user->relationship_status,
            'dob'          => $user->dating_dob ? $user->dating_dob->format('F Y') : null,
            'gender'       => $user->dating_gender,
            'location'     => $user->dating_location,
            'country'      => $user->dating_country,
        ], 'Identity & location fetched successfully', 200);
    }

    // ─────────────────────────────────────────────
    // VISUAL INFO TAB
    // ─────────────────────────────────────────────

    public function visualInfo($id)
    {
        $user = $this->resolveUser($id);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        $images = $user->datingProfile
            ? $user->datingProfile->images()->get()->map(fn($img) => [
                'id'          => $img->id,
                'url'         => $img->full_url,
                'description' => $user->visual_description,
            ])
            : [];

        return $this->success([
            'photos' => $images,
        ], 'Visual info fetched successfully', 200);
    }

    // ─────────────────────────────────────────────
    // APPEARANCE & LIFESTYLE TAB
    // ─────────────────────────────────────────────

    public function appearanceLifestyle($id)
    {
        $user = $this->resolveUser($id);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        return $this->success([
            'height'             => $user->height,
            'occupation'         => $user->occupation,
            'lifestyle_habits'   => $user->lifestyle_habits,
            'body_type'          => $user->body_type,
            'ethnicity'          => $user->ethnicity,
            'religious_beliefs'  => $user->religious_beliefs,
            'languages'          => $user->languages ?? [],
        ], 'Appearance & lifestyle fetched successfully', 200);
    }

    // ─────────────────────────────────────────────
    // INTERESTS & PERSONALITY TAB
    // ─────────────────────────────────────────────

    public function interestsPersonality($id)
    {
        $user = $this->resolveUser($id);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        return $this->success([
            'hobbies'             => $user->hobbies ?? [],
            'personality_traits'  => $user->personality_traits ?? [],
            'pet_preference'      => $user->pet_preference,
            'political_views'     => $user->political_views,
            'family_plans'        => $user->family_plans,
            'children_status'     => $user->children_status,
        ], 'Interests & personality fetched successfully', 200);
    }

    // ─────────────────────────────────────────────
    // MATCHING CRITERIA TAB
    // ─────────────────────────────────────────────

    public function matchingCriteria($id)
    {
        $user = $this->resolveUser($id);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        return $this->success([
            'relationship_goal'   => $user->relationship_goal,
            'deal_breakers'       => $user->deal_breakers,
            'partner_preferences' => $user->partner_preferences,
        ], 'Matching criteria fetched successfully', 200);
    }

    // ─────────────────────────────────────────────
    // KNOWLEDGE BASE TAB
    // ─────────────────────────────────────────────

    public function knowledgeBase($id)
    {
        $user = $this->resolveUser($id);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        // AI Knowledge Base is private per our earlier discussion
        // ("This document is not shared with anyone else")
        return $this->error([], 'This information is private.', 403);
    }
}
