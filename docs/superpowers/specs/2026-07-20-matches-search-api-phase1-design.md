# Matches Search API — Phase 1 Design

## Purpose

A traditional REST browse/search endpoint for discovering dating candidates — tabs + structured filters, no AI conversation involved. This is separate from the existing AI-driven Matches workspace (`WorkspaceConversationService`), which remains untouched.

Modeled on the existing Feed Search module's tabs/filter pattern (`UserFeedTopic`, `PostController::feed()`) and reuses the Friends module's favorites system (`SavedProfile`) as-is, but is **fully independent**: no shared tables, models, controllers, or routes with Feed or Post. Only the architectural pattern is mirrored.

**Phase 1 scope:** tabs API + the core search/browse endpoint. **Phase 2** (separate follow-up): profile detail endpoint + mutual-friends computation.

## Section 1 — Schema

### `match_topics` table (new migration)

Fully independent of `user_feed_topics` — no shared code.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | bigint, nullable, FK → users | `null` = fixed/shared topic |
| `name` | string | |
| `slug` | string, nullable | set for fixed topics only |
| `icon` | string, nullable | |
| `tag_keywords` | json, nullable | used by custom-tab keyword matching |
| `sort_order` | integer, default 0 | |
| `is_fixed` | boolean, default false | |
| `is_active` | boolean, default true | |
| timestamps | | |

New `App\Models\MatchTopic` Eloquent model — no shared code with `UserFeedTopic`.

### `dating_preferences.is_open_to_dating` (new migration)

- `boolean`, `default true`, added to the existing `dating_preferences` table.
- `DatingPreference::$fillable` and `$casts` updated to include it.
- Semantics: a global switch. When `false`, the user is excluded from **every** Matches Search tab (see Section 3) — same precedent as `DatingProfile.is_active` already gating the AI Matches workspace's candidate pool.

### `MatchTopicSeeder` (new)

Seeds the 6 fixed tabs, each with `user_id = null`, `is_fixed = true`, `is_active = true`:

| slug | name |
|---|---|
| `newest` | Newest |
| `local` | Local |
| `friendship` | Friendship |
| `long_term` | Long Term |
| `marriage` | Marriage |
| `open_to_dating` | Open to Dating |

Registered in `DatabaseSeeder` alongside `DatingProfileSeeder`.

This section touches nothing in `user_feed_topics`, `PostController`, or `FeedSearchController`.

## Section 2 — Tabs API

New `App\Http\Controllers\API\MatchTopicController`, mirroring `UserFeedTopicController`'s structure but operating on `MatchTopic`:

- **`GET /matches/topics`** — fixed tabs (`user_id IS NULL`) unioned with the caller's own custom tabs, ordered `is_fixed DESC, sort_order, created_at`. Served via a new `MatchTopicResource` (not the Feed one): `{id, name, slug, icon, is_fixed, created_at}`.
- **`POST /matches/topics`** — create a custom tab. Capped at `MAX_CUSTOM_TOPICS = 5` per user. Case-insensitive duplicate-name check against fixed tabs + the caller's own tabs. New tab: `user_id = caller`, `is_fixed = false`.
- **`DELETE /matches/topics/{id}`** — delete one of the caller's own custom tabs. 404 if it doesn't belong to the caller (fixed tabs, `user_id IS NULL`, never match).

Custom tabs have no `tag_keywords` set by the user; search falls back to matching the lowercased tab name against candidate text fields (see Section 3).

## Section 3 — Search/Browse Endpoint

**`GET /matches/search`** — new, independent `App\Http\Controllers\API\MatchSearchController`.

### Query params

| Param | Values | Default |
|---|---|---|
| `topic_id` | id from `GET /matches/topics` | — |
| `tab` | `newest\|local\|friendship\|long_term\|marriage\|open_to_dating` (legacy-alias, like Feed's `category`) | `newest` |
| `gender` | `male\|female\|both` | caller's `DatingPreference.interested_in` |
| `min_age` / `max_age` | integer | caller's `DatingPreference.min_age`/`max_age` |
| `max_distance` | integer km | caller's `DatingPreference.max_distance` |
| `relationship_goal` | `casual\|long_term\|marriage\|friendship\|not_sure` | none (no extra narrowing) |
| `per_page` | 1–50 | 15 |

Filter scope is intentionally limited to `DatingPreference` fields only (not the full `DatingProfile` breadth) — matches the AI Matches workspace's existing filter surface.

### Base candidate query (always applied, every tab)

- Exclude self; `users.status = 'active'`.
- Has a completed dating profile: `datingProfile` exists with `is_active = true`; `datingPreference` exists (same completeness bar as `User::hasCompletedDatingProfile()`).
- **`datingPreference.is_open_to_dating = true`** — global gate applied regardless of tab.
- Exclude blocked users in either direction (`UserBlock`, same pattern as `PostController::feed()`).
- Gender filter: candidate's `dating_gender` matches the effective `gender` value (skipped entirely when `both`).
- Age filter: age computed from `dating_dob`, must fall within `min_age`/`max_age`.
- Distance filter: applied only when the caller has `latitude`/`longitude` set and an effective `max_distance` is present — haversine, same SQL formula as Feed's `local` tab.
- `relationship_goal` query param, if explicitly passed, filters `datingPreference.relationship_goal` on top of whatever the tab itself implies.

### Tab dispatch (applied on top of the base query)

- **`newest`** — `orderByDesc('dating_profiles.created_at')`.
- **`local`** — requires caller `latitude`/`longitude` to be set; **422** with a message (same style as Feed's local-tab guard) if missing. Orders by computed distance ascending.
- **`friendship`** — `datingPreference.relationship_goal = 'friendship'`.
- **`long_term`** — `datingPreference.relationship_goal = 'long_term'`.
- **`marriage`** — `datingPreference.relationship_goal = 'marriage'`.
- **`open_to_dating`** — no additional filter beyond the base query (which already gates on `is_open_to_dating`); default/newest ordering. This tab is the dedicated entry point for "everyone currently open," distinct from `newest`'s recency framing but drawing from the same pool.
- **Custom tabs** (`MatchTopic` rows with `user_id` = caller) — keyword match: lowercased `tag_keywords` if set, else the tab's lowercased `name`. Matched against `about` and `occupation` via `LIKE '%keyword%'`, and against `hobbies` (a JSON array column) via `whereJsonContains`. Same fallback pattern as Feed's custom topics, adapted for `hobbies`' JSON type.

### Response shape

Mirrors `FriendController::search()`'s pattern (`relation_status` / `is_favorite`), computed identically via `UserConnection`, `ConnectionRequest`, and `SavedProfile` — zero new schema for these fields:

```json
{
  "users": [
    {
      "id": 42,
      "avatar": "https://.../user.png",
      "name": "Jane Doe",
      "username": "jane-doe-1",
      "age": 29,
      "city": "Austin",
      "about": "...",
      "occupation": "Software Engineer",
      "relationship_goal": "long_term",
      "distance_km": 12.4,
      "relation_status": "none",
      "is_favorite": false
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  }
}
```

- `distance_km` is present only when `tab=local`.
- `relation_status` ∈ `none | connected | pending_sent | pending_received`.
- Favoriting itself is out of scope for this endpoint — reuses the existing `POST/DELETE /friends/favorites/{id}` routes unchanged.
- `avatar` follows the same convention as the AI Matches workspace's `storeMatchSuggestions()`: `asset($candidate->avatar ?? 'user.png')`.
- `age` is computed from `dating_dob` (`Carbon::parse($dob)->age`), not stored.

## Section 4 — Testing Approach

New feature test files, isolated from existing Feed/Friends test suites:

**`tests/Feature/Matches/MatchTopicApiTest.php`**
- List returns the 6 fixed tabs plus the caller's own custom tabs, never another user's custom tabs.
- Create succeeds up to 5 custom tabs; a 6th is rejected (422).
- Duplicate name (case-insensitive, against fixed or own tabs) is rejected (422).
- Delete removes the caller's own custom tab.
- Delete is rejected (404) for a fixed tab or another user's tab.

**`tests/Feature/Matches/MatchSearchApiTest.php`**
- Each of the 6 tabs returns only correctly-filtered candidates, using small hand-built fixtures per test (not the 100-row `DatingProfileSeeder`).
- A candidate with `is_open_to_dating = false` never appears, in any tab, including `open_to_dating`.
- `gender`, `min_age`/`max_age`, `max_distance`, and `relationship_goal` filters each correctly narrow results.
- `local` tab returns 422 when the caller has no `latitude`/`longitude`.
- Blocked users (either direction) are excluded from every tab.
- `relation_status` and `is_favorite` correctly reflect `UserConnection`/`ConnectionRequest`/`SavedProfile` state.
- Pagination metadata is correct (`current_page`, `per_page`, `total`, `last_page`).
- A custom tab's keyword matching correctly filters against `about`/`hobbies`/`occupation`.

Run via `php artisan test --filter=Matches`, against the MySQL test database (not sqlite — matches this project's existing `phpunit.xml` configuration).

## Out of Scope (Phase 2)

- Profile detail endpoint.
- Mutual-friends computation (inline count + preview, and a full paginated on-demand list) — no existing implementation to build on; net-new logic.
- Any further wiring of favorites beyond reusing the existing `SavedProfile`/`FriendController` routes as-is.
