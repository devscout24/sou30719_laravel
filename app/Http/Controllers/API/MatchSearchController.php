<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ConnectionRequest;
use App\Models\MatchTopic;
use App\Models\SavedProfile;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserConnection;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MatchSearchController extends Controller
{
    use ApiResponse;

    /**
     * Tabbed, filterable browse/search over dating candidates.
     *
     * Query params:
     *   topic_id           integer  — id from GET /matches/topics (fixed or the user's own custom tab)
     *   tab                string   — legacy-alias fixed-tab slug (default: newest)
     *   gender             male|female|both                      (default: caller's DatingPreference.interested_in)
     *   min_age / max_age  integer                                (default: caller's DatingPreference.min_age/max_age)
     *   max_distance       integer km                             (default: caller's DatingPreference.max_distance)
     *   relationship_goal  casual|long_term|marriage|friendship|not_sure  (default: none)
     *   per_page           1-50                                   (default: 15)
     */
    public function search(Request $request)
    {
        $userId = Auth::guard('api')->id();
        $user   = User::find($userId);

        $topicId  = $request->query('topic_id');
        $tabParam = $request->query('tab');
        $perPage  = min(max((int) $request->query('per_page', 15), 1), 50);

        $topic = $this->resolveMatchTopic($userId, $topicId, $tabParam);

        if (!$topic) {
            return $this->error([], 'Topic not found', 404);
        }

        if ($topic->slug === 'local' && (blank($user?->latitude) || blank($user?->longitude))) {
            return $this->error(
                [],
                'Location not set. Please update your location to use the Local tab.',
                422
            );
        }

        $preference = $user?->datingPreference;

        $gender           = $request->query('gender', $preference?->interested_in ?? 'both');
        $minAge           = $request->query('min_age', $preference?->min_age);
        $maxAge           = $request->query('max_age', $preference?->max_age);
        $maxDistance      = $request->query('max_distance', $preference?->max_distance);
        $relationshipGoal = $request->query('relationship_goal');

        $blockedIds = UserBlock::where('user_id', $userId)
            ->orWhere('blocked_user_id', $userId)
            ->get()
            ->flatMap(fn ($b) => [$b->user_id, $b->blocked_user_id])
            ->filter(fn ($id) => $id !== $userId)
            ->unique()
            ->values()
            ->all();

        $query = User::query()
            ->where('users.id', '!=', $userId)
            ->where('users.status', 'active')
            ->whereNotIn('users.id', $blockedIds)
            ->whereHas('datingProfile', fn ($q) => $q->where('is_active', true))
            ->whereHas('datingPreference', function ($q) use ($relationshipGoal) {
                $q->where('is_open_to_dating', true);

                if ($relationshipGoal) {
                    $q->where('relationship_goal', $relationshipGoal);
                }
            })
            ->with(['datingProfile', 'datingPreference']);

        if ($gender !== 'both') {
            $query->whereHas('datingProfile', fn ($q) => $q->where('dating_gender', $gender));
        }

        if ($minAge !== null || $maxAge !== null) {
            $query->whereHas('datingProfile', function ($q) use ($minAge, $maxAge) {
                $q->whereNotNull('dating_dob');

                if ($minAge !== null) {
                    $q->where('dating_dob', '<=', now()->subYears((int) $minAge)->toDateString());
                }

                if ($maxAge !== null) {
                    $q->where('dating_dob', '>=', now()->subYears((int) $maxAge + 1)->addDay()->toDateString());
                }
            });
        }

        if ($maxDistance !== null && filled($user?->latitude) && filled($user?->longitude)) {
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

            $query->whereIn('users.id', $nearbyIds);
        }

        $this->applyTabFilter($query, $topic, $user);

        $candidates = $query->paginate($perPage);

        $connectionIds = UserConnection::forUser($userId)
            ->get()
            ->map(fn ($c) => $c->otherUser($userId)?->id)
            ->filter()
            ->all();

        $sentIds     = ConnectionRequest::where('sender_id', $userId)->pending()->pluck('receiver_id')->all();
        $receivedIds = ConnectionRequest::where('receiver_id', $userId)->pending()->pluck('sender_id')->all();
        $favoriteIds = SavedProfile::where('user_id', $userId)->pluck('saved_user_id')->all();

        $isLocalTab = $topic->slug === 'local';
        $callerLat  = $user?->latitude;
        $callerLng  = $user?->longitude;

        $items = collect($candidates->items())->map(function (User $candidate) use (
            $connectionIds, $sentIds, $receivedIds, $favoriteIds, $isLocalTab, $callerLat, $callerLng
        ) {
            $status = 'none';

            if (in_array($candidate->id, $connectionIds)) {
                $status = 'connected';
            } elseif (in_array($candidate->id, $sentIds)) {
                $status = 'pending_sent';
            } elseif (in_array($candidate->id, $receivedIds)) {
                $status = 'pending_received';
            }

            return $this->formatCandidate(
                $candidate,
                $status,
                in_array($candidate->id, $favoriteIds),
                $isLocalTab,
                $callerLat,
                $callerLng
            );
        });

        return $this->success([
            'users' => $items->values(),
            'pagination' => [
                'current_page' => $candidates->currentPage(),
                'per_page'     => $candidates->perPage(),
                'total'        => $candidates->total(),
                'last_page'    => $candidates->lastPage(),
            ],
        ], $candidates->isEmpty() ? 'No matches found' : 'Matches fetched successfully');
    }

    /**
     * Resolve the requested tab — by id (fixed or the user's own custom tab),
     * or by the legacy 'tab' slug alias, defaulting to 'newest'.
     */
    protected function resolveMatchTopic(int $userId, ?string $topicId, ?string $tab): ?MatchTopic
    {
        if ($topicId) {
            return MatchTopic::where('id', $topicId)
                ->where('is_active', true)
                ->where(function ($q) use ($userId) {
                    $q->whereNull('user_id')->orWhere('user_id', $userId);
                })
                ->first();
        }

        return MatchTopic::where('slug', $tab ?: 'newest')
            ->whereNull('user_id')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Apply the resolved tab's filtering/ordering to the candidate query, in place.
     */
    protected function applyTabFilter($query, MatchTopic $topic, ?User $user): void
    {
        switch ($topic->slug) {
            case 'local':
                $lat = (float) $user->latitude;
                $lng = (float) $user->longitude;

                $query->orderByRaw(
                    '(6371 * acos(cos(radians(?)) * cos(radians(users.latitude)) * cos(radians(users.longitude) - radians(?)) + sin(radians(?)) * sin(radians(users.latitude)))) asc',
                    [$lat, $lng, $lat]
                );
                break;

            case 'friendship':
            case 'long_term':
            case 'marriage':
                $query->whereHas('datingPreference', fn ($q) => $q->where('relationship_goal', $topic->slug));
                $query->latest('users.created_at');
                break;

            case 'open_to_dating':
            case 'newest':
                $query->latest('users.created_at');
                break;

            default:
                // Every custom user tab.
                $topicName = mb_strtolower($topic->name);
                $keywords  = !empty($topic->tag_keywords) ? $topic->tag_keywords : [$topicName];

                $query->whereHas('datingProfile', function ($q) use ($keywords) {
                    $q->where(function ($inner) use ($keywords) {
                        foreach ($keywords as $keyword) {
                            $lower = mb_strtolower($keyword);
                            $inner->orWhere('about', 'like', "%{$lower}%")
                                ->orWhere('occupation', 'like', "%{$lower}%")
                                ->orWhereJsonContains('hobbies', $lower);
                        }
                    });
                });
                $query->latest('users.created_at');
                break;
        }
    }

    protected function formatCandidate(
        User $candidate,
        string $status,
        bool $isFavorite,
        bool $isLocalTab,
        ?string $callerLat,
        ?string $callerLng
    ): array {
        $profile = $candidate->datingProfile;

        $data = [
            'id'                => $candidate->id,
            'avatar'            => asset($candidate->avatar ?? 'user.png'),
            'name'              => $candidate->name,
            'username'          => $candidate->username,
            'age'               => $profile?->dating_dob?->age,
            'city'              => $profile?->dating_location ?? $profile?->city,
            'about'             => $profile?->about ?? $profile?->about_me,
            'occupation'        => $profile?->occupation,
            'relationship_goal' => $candidate->datingPreference?->relationship_goal,
            'relation_status'   => $status,
            'is_favorite'       => $isFavorite,
        ];

        if ($isLocalTab && filled($callerLat) && filled($callerLng) && filled($candidate->latitude) && filled($candidate->longitude)) {
            $data['distance_km'] = round($this->haversineKm(
                (float) $callerLat, (float) $callerLng, (float) $candidate->latitude, (float) $candidate->longitude
            ), 1);
        }

        return $data;
    }

    protected function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
