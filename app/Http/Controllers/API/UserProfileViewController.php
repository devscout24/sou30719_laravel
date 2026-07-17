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
    private function resolveUser(string $username)
    {
        $authUserId = Auth::guard('api')->id();

        $user = User::query()->where('username', $username)->first();

        if (!$user) {
            return null;
        }

        // Don't show profile if either side has blocked the other
        if (\App\Models\UserBlock::isBlocked($authUserId, $user->id)) {
            return null;
        }

        return $user;
    }

    // ─────────────────────────────────────────────
    // BASIC INFO TAB
    // ─────────────────────────────────────────────

    public function basicInfo($username)
    {
        $user = $this->resolveUser($username);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        $datingNickname = optional($user->datingProfile)->dating_nickname;

        return $this->success([
            'name'      => $datingNickname
                ? "{$user->name} ({$datingNickname})"
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

    public function gallery($username)
    {
        $user = $this->resolveUser($username);

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

    public function identityLocation($username)
    {
        $user = $this->resolveUser($username);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        $datingProfile = $user->datingProfile;

        // Respect the user's own "connections_view" visibility toggle
        if (!optional($datingProfile)->connections_view) {
            return $this->error([], 'This user has hidden their identity & location info.', 403);
        }

        return $this->success([
            'nick_name'    => $datingProfile->dating_nickname,
            'status'       => $datingProfile->relationship_status,
            'dob'          => $datingProfile->dating_dob ? $datingProfile->dating_dob->format('F Y') : null,
            'gender'       => $datingProfile->dating_gender,
            'location'     => $datingProfile->dating_location,
            'country'      => $datingProfile->dating_country,
        ], 'Identity & location fetched successfully', 200);
    }

    // ─────────────────────────────────────────────
    // VISUAL INFO TAB
    // ─────────────────────────────────────────────

    public function visualInfo($username)
    {
        $user = $this->resolveUser($username);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        $images = $user->datingProfile
            ? $user->datingProfile->images()->get()->map(fn($img) => [
                'id'          => $img->id,
                'url'         => $img->full_url,
                'description' => $user->datingProfile->visual_description,
            ])
            : [];

        return $this->success([
            'photos' => $images,
        ], 'Visual info fetched successfully', 200);
    }

    // ─────────────────────────────────────────────
    // APPEARANCE & LIFESTYLE TAB
    // ─────────────────────────────────────────────

    public function appearanceLifestyle($username)
    {
        $user = $this->resolveUser($username);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        $datingProfile = $user->datingProfile;

        return $this->success([
            'height'             => optional($datingProfile)->height,
            'occupation'         => optional($datingProfile)->occupation,
            'lifestyle_habits'   => optional($datingProfile)->lifestyle_habits,
            'body_type'          => optional($datingProfile)->body_type,
            'ethnicity'          => optional($datingProfile)->ethnicity,
            'religious_beliefs'  => optional($datingProfile)->religious_beliefs,
            'languages'          => optional($datingProfile)->languages ?? [],
        ], 'Appearance & lifestyle fetched successfully', 200);
    }

    // ─────────────────────────────────────────────
    // INTERESTS & PERSONALITY TAB
    // ─────────────────────────────────────────────

    public function interestsPersonality($username)
    {
        $user = $this->resolveUser($username);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        $datingProfile = $user->datingProfile;

        return $this->success([
            'hobbies'             => optional($datingProfile)->hobbies ?? [],
            'personality_traits'  => optional($datingProfile)->personality_traits ?? [],
            'pet_preference'      => optional($datingProfile)->pet_preference,
            'political_views'     => optional($datingProfile)->political_views,
            'family_plans'        => optional($datingProfile)->family_plans,
            'children_status'     => optional($datingProfile)->children_status,
        ], 'Interests & personality fetched successfully', 200);
    }

    // ─────────────────────────────────────────────
    // MATCHING CRITERIA TAB
    // ─────────────────────────────────────────────

    public function matchingCriteria($username)
    {
        $user = $this->resolveUser($username);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        $pref = $user->datingPreference;

        return $this->success([
            'relationship_goal'   => optional($pref)->relationship_goal,
            'deal_breakers'       => optional($pref)->deal_breakers,
            'partner_preferences' => optional($pref)->partner_preferences,
        ], 'Matching criteria fetched successfully', 200);
    }

    // ─────────────────────────────────────────────
    // KNOWLEDGE BASE TAB
    // ─────────────────────────────────────────────

    public function knowledgeBase($username)
    {
        $user = $this->resolveUser($username);

        if (!$user) {
            return $this->error([], 'Profile not found.', 404);
        }

        // AI Knowledge Base is private per our earlier discussion
        // ("This document is not shared with anyone else")
        return $this->error([], 'This information is private.', 403);
    }
}
