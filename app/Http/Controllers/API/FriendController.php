<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ConnectionRequest;
use App\Models\SavedProfile;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserConnection;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class FriendController extends Controller
{
    use ApiResponse;

    // ─────────────────────────────────────────────
    // PAGE 1 — CONNECTED
    // ─────────────────────────────────────────────

    public function connected(Request $request)
    {
        $userId = Auth::guard('api')->id();
        [$perPage, $page, $search] = $this->paginationParams($request);

        $blockedIds = $this->blockedIds($userId);

        $friends = $this->friendsOf($userId, $search)
            ->filter(fn ($u) => !in_array($u->id, $blockedIds))
            ->values();

        $paginated = $this->paginateCollection($friends, $perPage, $page);

        return $this->success([
            'friends'    => collect($paginated->items())->map(fn ($u) => $this->formatUser($u))->values(),
            'pagination' => $this->paginationMeta($paginated),
        ], 'Connected friends fetched successfully');
    }

    // ─────────────────────────────────────────────
    // CURATE — a friend's own friends list
    // ─────────────────────────────────────────────

    public function curate(string $username, Request $request)
    {
        $authId = Auth::guard('api')->id();

        $target = User::where('username', $username)->first();

        if (!$target) {
            return $this->error([], 'User not found', 404);
        }

        if (UserBlock::isBlocked($authId, $target->id)) {
            return $this->error([], 'This user is unavailable', 403);
        }

        [$perPage, $page, $search] = $this->paginationParams($request);

        $blockedIds = $this->blockedIds($authId);

        $friends = $this->friendsOf($target->id, $search)
            ->filter(fn ($u) => $u->id !== $authId && !in_array($u->id, $blockedIds))
            ->values();

        $paginated = $this->paginateCollection($friends, $perPage, $page);

        return $this->success([
            'user'       => $this->formatUser($target),
            'friends'    => collect($paginated->items())->map(fn ($u) => $this->formatUser($u))->values(),
            'pagination' => $this->paginationMeta($paginated),
        ], 'Friends fetched successfully');
    }

    // ─────────────────────────────────────────────
    // PAGE 2 — REQUESTS
    // ─────────────────────────────────────────────

    public function sentRequests(Request $request)
    {
        $userId = Auth::guard('api')->id();
        [$perPage, $page, $search] = $this->paginationParams($request);

        $requests = ConnectionRequest::where('sender_id', $userId)
            ->pending()
            ->with('receiver')
            ->latest()
            ->get()
            ->filter(fn ($r) => $r->receiver !== null)
            ->when($search !== '', fn (Collection $c) => $c->filter(
                fn ($r) => stripos($r->receiver->name ?? '', $search) !== false
            ))
            ->values();

        $paginated = $this->paginateCollection($requests, $perPage, $page);

        return $this->success([
            'requests' => collect($paginated->items())->map(fn ($r) => array_merge(
                $this->formatUser($r->receiver),
                ['request_id' => $r->id, 'requested_at' => $r->created_at]
            ))->values(),
            'pagination' => $this->paginationMeta($paginated),
        ], 'Sent requests fetched successfully');
    }

    public function receivedRequests(Request $request)
    {
        $userId = Auth::guard('api')->id();
        [$perPage, $page, $search] = $this->paginationParams($request);

        $requests = ConnectionRequest::where('receiver_id', $userId)
            ->pending()
            ->with('sender')
            ->latest()
            ->get()
            ->filter(fn ($r) => $r->sender !== null)
            ->when($search !== '', fn (Collection $c) => $c->filter(
                fn ($r) => stripos($r->sender->name ?? '', $search) !== false
            ))
            ->values();

        $paginated = $this->paginateCollection($requests, $perPage, $page);

        return $this->success([
            'requests' => collect($paginated->items())->map(fn ($r) => array_merge(
                $this->formatUser($r->sender),
                ['request_id' => $r->id, 'requested_at' => $r->created_at]
            ))->values(),
            'pagination' => $this->paginationMeta($paginated),
        ], 'Received requests fetched successfully');
    }

    /**
     * Send (or resend) a connection request to another user.
     * If the target already has a pending request to us, it's accepted instead.
     */
    public function sendRequest(int $id)
    {
        $userId = Auth::guard('api')->id();

        if ($userId === $id) {
            return $this->error([], 'You cannot send a request to yourself', 422);
        }

        if (!User::find($id)) {
            return $this->error([], 'User not found', 404);
        }

        if (UserBlock::isBlocked($userId, $id)) {
            return $this->error([], 'You cannot connect with this user', 422);
        }

        if ($this->areConnected($userId, $id)) {
            return $this->error([], 'You are already connected with this user', 422);
        }

        $reverse = ConnectionRequest::where('sender_id', $id)
            ->where('receiver_id', $userId)
            ->pending()
            ->first();

        if ($reverse) {
            $reverse->accept();
            $this->createConnection($userId, $id, $reverse->id);

            return $this->success([], 'You are now connected');
        }

        $existing = ConnectionRequest::where('sender_id', $userId)
            ->where('receiver_id', $id)
            ->first();

        if ($existing && $existing->isPending()) {
            return $this->error([], 'Request already sent', 422);
        }

        if ($existing) {
            $existing->update(['status' => 'pending']);
        } else {
            ConnectionRequest::create([
                'sender_id'   => $userId,
                'receiver_id' => $id,
                'status'      => 'pending',
            ]);
        }

        return $this->success([], 'Connection request sent successfully');
    }

    /**
     * Accept an incoming request from $id.
     */
    public function acceptRequest(int $id)
    {
        $userId = Auth::guard('api')->id();

        $request = ConnectionRequest::where('sender_id', $id)
            ->where('receiver_id', $userId)
            ->pending()
            ->first();

        if (!$request) {
            return $this->error([], 'No pending request from this user', 404);
        }

        $request->accept();
        $this->createConnection($userId, $id, $request->id);

        return $this->success([], 'Connection request accepted');
    }

    /**
     * Reject an incoming request from $id.
     */
    public function rejectRequest(int $id)
    {
        $userId = Auth::guard('api')->id();

        $request = ConnectionRequest::where('sender_id', $id)
            ->where('receiver_id', $userId)
            ->pending()
            ->first();

        if (!$request) {
            return $this->error([], 'No pending request from this user', 404);
        }

        $request->reject();

        return $this->success([], 'Connection request rejected');
    }

    /**
     * Cancel a request we previously sent to $id.
     */
    public function cancelRequest(int $id)
    {
        $userId = Auth::guard('api')->id();

        $request = ConnectionRequest::where('sender_id', $userId)
            ->where('receiver_id', $id)
            ->pending()
            ->first();

        if (!$request) {
            return $this->error([], 'No pending request to cancel', 404);
        }

        $request->delete();

        return $this->success([], 'Connection request cancelled');
    }

    // ─────────────────────────────────────────────
    // PAGE 3 — FAVOURITES
    // ─────────────────────────────────────────────

    public function favorites(Request $request)
    {
        $userId = Auth::guard('api')->id();
        [$perPage, $page, $search] = $this->paginationParams($request);

        $favorites = SavedProfile::where('user_id', $userId)
            ->with('savedUser')
            ->latest()
            ->get()
            ->filter(fn ($f) => $f->savedUser !== null)
            ->when($search !== '', fn (Collection $c) => $c->filter(
                fn ($f) => stripos($f->savedUser->name ?? '', $search) !== false
            ))
            ->values();

        $paginated = $this->paginateCollection($favorites, $perPage, $page);

        return $this->success([
            'favorites' => collect($paginated->items())->map(
                fn ($f) => array_merge($this->formatUser($f->savedUser), ['is_favorite' => true])
            )->values(),
            'pagination' => $this->paginationMeta($paginated),
        ], 'Favourite friends fetched successfully');
    }

    public function addFavorite(int $id)
    {
        $userId = Auth::guard('api')->id();

        if ($userId === $id) {
            return $this->error([], 'You cannot favourite yourself', 422);
        }

        if (!User::find($id)) {
            return $this->error([], 'User not found', 404);
        }

        if (UserBlock::isBlocked($userId, $id)) {
            return $this->error([], 'You cannot favourite this user', 422);
        }

        SavedProfile::firstOrCreate([
            'user_id'       => $userId,
            'saved_user_id' => $id,
        ]);

        return $this->success([], 'Added to favourites');
    }

    public function removeFavorite(int $id)
    {
        $userId = Auth::guard('api')->id();

        $favorite = SavedProfile::where('user_id', $userId)
            ->where('saved_user_id', $id)
            ->first();

        if (!$favorite) {
            return $this->error([], 'This user is not in your favourites', 404);
        }

        $favorite->delete();

        return $this->success([], 'Removed from favourites');
    }

    // ─────────────────────────────────────────────
    // SEARCH
    // ─────────────────────────────────────────────

    public function search(Request $request)
    {
        $userId = Auth::guard('api')->id();
        $search = trim((string) $request->query('query', $request->query('search', '')));
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $blockedIds = $this->blockedIds($userId);

        $usersQuery = User::query()
            ->where('id', '!=', $userId)
            ->whereNotIn('id', $blockedIds);

        if ($search !== '') {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $usersQuery->orderBy('name')->paginate($perPage);

        $connectionIds = UserConnection::forUser($userId)
            ->get()
            ->map(fn ($c) => $c->user_one_id === $userId ? $c->user_two_id : $c->user_one_id)
            ->all();

        $sentIds = ConnectionRequest::where('sender_id', $userId)->pending()->pluck('receiver_id')->all();
        $receivedIds = ConnectionRequest::where('receiver_id', $userId)->pending()->pluck('sender_id')->all();
        $favoriteIds = SavedProfile::where('user_id', $userId)->pluck('saved_user_id')->all();

        $items = collect($users->items())->map(function (User $u) use ($connectionIds, $sentIds, $receivedIds, $favoriteIds) {
            $status = 'none';

            if (in_array($u->id, $connectionIds)) {
                $status = 'connected';
            } elseif (in_array($u->id, $sentIds)) {
                $status = 'pending_sent';
            } elseif (in_array($u->id, $receivedIds)) {
                $status = 'pending_received';
            }

            return array_merge($this->formatUser($u), [
                'relation_status' => $status,
                'is_favorite'     => in_array($u->id, $favoriteIds),
            ]);
        });

        return $this->success([
            'users' => $items->values(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
                'last_page'    => $users->lastPage(),
            ],
        ], 'Users fetched successfully');
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    private function formatUser(User $user): array
    {
        return [
            'id'       => $user->id,
            'name'     => $user->name,
            'username' => $user->username,
            'avatar'   => asset($user->avatar ?? 'user.png'),
            'location' => $user->location,
        ];
    }

    /**
     * All of $userId's connected friends (unpaginated), optionally filtered by name.
     */
    private function friendsOf(int $userId, string $search = ''): Collection
    {
        return UserConnection::forUser($userId)
            ->with(['userOne', 'userTwo'])
            ->get()
            ->map(fn ($c) => $c->otherUser($userId))
            ->filter(fn ($u) => $u !== null)
            ->when($search !== '', fn (Collection $c) => $c->filter(
                fn ($u) => stripos($u->name ?? '', $search) !== false
            ))
            ->values();
    }

    private function areConnected(int $a, int $b): bool
    {
        [$one, $two] = $a < $b ? [$a, $b] : [$b, $a];

        return UserConnection::where('user_one_id', $one)->where('user_two_id', $two)->exists();
    }

    private function createConnection(int $a, int $b, ?int $requestId): UserConnection
    {
        [$one, $two] = $a < $b ? [$a, $b] : [$b, $a];

        return UserConnection::firstOrCreate(
            ['user_one_id' => $one, 'user_two_id' => $two],
            ['connection_request_id' => $requestId, 'connected_at' => now()]
        );
    }

    /**
     * IDs of users blocked in either direction with $userId.
     */
    private function blockedIds(int $userId): array
    {
        return UserBlock::where('user_id', $userId)
            ->orWhere('blocked_user_id', $userId)
            ->get()
            ->flatMap(fn ($b) => [$b->user_id, $b->blocked_user_id])
            ->filter(fn ($id) => $id !== $userId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{0: int, 1: int, 2: string} [perPage, page, search]
     */
    private function paginationParams(Request $request): array
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $page = max((int) $request->query('page', 1), 1);
        $search = trim((string) $request->query('search', ''));

        return [$perPage, $page, $search];
    }

    private function paginateCollection(Collection $items, int $perPage, int $page): LengthAwarePaginator
    {
        $items = $items->values();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($slice, $items->count(), $perPage, $page, [
            'path'  => request()->url(),
            'query' => request()->query(),
        ]);
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'last_page'    => $paginator->lastPage(),
        ];
    }
}
