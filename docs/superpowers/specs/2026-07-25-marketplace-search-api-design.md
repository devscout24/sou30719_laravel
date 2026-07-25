# Marketplace Search API — Design

## Purpose

A traditional REST browse/search endpoint for the Marketplace's "All Ads" screen — tabs + structured filters, no AI conversation involved. This is separate from the existing AI-driven Marketplace ad-creation workspace (`WorkspaceConversationService::handleMarketplaceCollecting`), which remains untouched: ads are already created and stored there as `Post` rows with `type = 'ad'`.

Modeled directly on the Matches Search Phase 1 module (`MatchTopic`, `MatchSearchController`), which itself mirrors Feed's tabs/filter pattern (`UserFeedTopic`, `PostController::feed()`). Fully independent: no shared tables, models, controllers, or routes with Feed or Matches — only the architectural pattern is mirrored.

## Section 1 — Schema

### `marketplace_topics` table (new migration)

Identical shape to `match_topics` — fully independent, no shared code.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | bigint, nullable, FK → users | `null` = fixed/shared topic |
| `name` | string | |
| `slug` | string, nullable | set for fixed topics only |
| `icon` | string, nullable | |
| `tag_keywords` | json, nullable | used by keyword-matched tabs (see Section 3) |
| `sort_order` | integer, default 0 | |
| `is_fixed` | boolean, default false | |
| `is_active` | boolean, default true | |
| timestamps | | |

New `App\Models\MarketplaceTopic` Eloquent model — no shared code with `MatchTopic` or `UserFeedTopic`.

### `MarketplaceTopicSeeder` (new)

Seeds the 5 fixed topics, each with `user_id = null`, `is_fixed = true`, `is_active = true`:

| slug | name |
|---|---|
| `products` | Products |
| `services` | Services |
| `cosmetics` | Cosmetics |
| `electronics` | Electronics |
| `food` | Food |

Registered in `DatabaseSeeder` alongside `MatchTopicSeeder`.

There is deliberately **no** "All"/"Newest" fixed topic. When the search endpoint (Section 3) receives no `topic_id`/`tab`, it returns all published ads, newest first — that's the "All Ads" view. The 5 topics are narrowing filters on top of it, not a required selection.

This section touches nothing in `match_topics`, `user_feed_topics`, `PostController`, `FeedSearchController`, or `MatchSearchController`.

## Section 2 — Topics API

New `App\Http\Controllers\API\MarketplaceTopicController`, mirroring `MatchTopicController`'s structure but operating on `MarketplaceTopic`:

- **`GET /marketplace/topics`** — fixed topics (`user_id IS NULL`) unioned with the caller's own custom topics, ordered `is_fixed DESC, sort_order, created_at`. Served via a new `MarketplaceTopicResource`: `{id, name, slug, icon, is_fixed, created_at}`.
- **`POST /marketplace/topics`** — create a custom topic. Capped at `MAX_CUSTOM_TOPICS = 5` per user. Case-insensitive duplicate-name check against fixed topics + the caller's own topics. New topic: `user_id = caller`, `is_fixed = false`. Validated via new `StoreMarketplaceTopicRequest` (`name` required, string, max:50).
- **`DELETE /marketplace/topics/{id}`** — delete one of the caller's own custom topics. 404 if it doesn't belong to the caller (fixed topics, `user_id IS NULL`, never match).

Custom topics have no `tag_keywords` set by the user; search falls back to matching the lowercased topic name against ad text fields (see Section 3).

## Section 3 — Search/Browse Endpoint

**`GET /marketplace/search`** — new, independent `App\Http\Controllers\API\MarketplaceSearchController`.

### Query params

| Param | Values | Default |
|---|---|---|
| `topic_id` | id from `GET /marketplace/topics` | — |
| `tab` | `products\|services\|cosmetics\|electronics\|food` (legacy-alias, like Feed's `category`) | none (all ads) |
| `min_price` / `max_price` | decimal | none |
| `max_distance` | integer km | none |
| `per_page` | 1–50 | 15 |

### Base candidate query (always applied)

- `Post::query()->ads()->published()->visible()` — reuses the existing `scopeAds`, `scopePublished`, `scopeVisible` on `Post` (`type = 'ad'`, `status = 'published'`, `visibility = 'public'`). No new scopes.
- Exclude blocked users in either direction (`UserBlock`), same pattern as `PostController::feed()` / `MatchSearchController::search()`.
- `min_price`/`max_price`, if passed, filter `Post.price` (`decimal:2` cast already in place).
- `max_distance`, if passed: requires the caller (`Auth` user) to have `latitude`/`longitude` set — **422** with a message if missing (same style as Feed's local-tab guard). Otherwise filters to ads whose poster (`Post.user`) is within `max_distance` km of the caller, using the same haversine SQL as `PostController::applyTopicFilter()`'s `local` case, applied via a `whereIn('user_id', $nearbyIds)` subquery against `users.latitude/longitude`.
- Always ordered `latest('published_at')` — no per-tab custom ordering (there's no "local" tab here, distance is a filter, not a sort).

### Topic dispatch (applied on top of the base query, only when a topic is resolved)

Topic resolution mirrors `MatchSearchController::resolveMatchTopic()`: by `topic_id` (fixed or the caller's own custom topic) or by the legacy `tab` slug alias. Unlike Feed/Matches, **no topic is required** — if neither `topic_id` nor `tab` is given, no topic filter is applied at all (the "All Ads" view).

- **`products`** — `where('ad_type', 'product')`. Exact field match (ad_type is a reliable enum, not fuzzy).
- **`services`** — `where('ad_type', 'service')`. Exact field match.
- **`cosmetics`** — keyword match. Default keywords `['cosmetics', 'beauty', 'skincare', 'makeup']` (used when the topic has no `tag_keywords` set — true for the seeded fixed topic).
- **`electronics`** — keyword match. Default keywords `['electronics', 'gadget', 'tech']`.
- **`food`** — keyword match. Default keywords `['food', 'grocery', 'restaurant', 'meal']`.
- **Custom tabs** (`MarketplaceTopic` rows with `user_id` = caller) — same keyword-match fallback: lowercased `tag_keywords` if set, else the topic's lowercased `name`.

Keyword matching (shared logic for cosmetics/electronics/food/custom): each keyword is matched via `LIKE '%keyword%'` against `category`, `title`, `short_description`, and `content`, plus `whereJsonContains('tags', $keyword)` — same field set and pattern as Feed's custom-topic fallback, adapted to ad fields (`category` added since it's ad-specific and already carries values like `electronics`).

### Response shape

Reuses the existing `App\Http\Resources\PostResource` as-is — already renders everything an ad card needs (`price`, `location`, `category`, `images`, `author`, `total_likes`, `is_liked`, etc.), and is the same resource `PostController::feed()` already uses for ads mixed into the social feed. No new resource.

```json
{
  "posts": [ { "...": "PostResource shape, unchanged" } ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 40,
    "last_page": 3
  }
}
```

Engagement (like/share/report) and detail view continue to use the existing `POST /posts/{slug}/like`, `/share`, `/report`, and `GET /posts/{slug}` routes unchanged — out of scope here.

## Section 4 — Testing Approach

New feature test files, isolated from existing Feed/Matches test suites:

**`tests/Feature/Marketplace/MarketplaceTopicApiTest.php`**
- List returns the 5 fixed topics plus the caller's own custom topics, never another user's custom topics.
- Create succeeds up to 5 custom topics; a 6th is rejected (422).
- Duplicate name (case-insensitive, against fixed or own topics) is rejected (422).
- Delete removes the caller's own custom topic.
- Delete is rejected (404) for a fixed topic or another user's topic.

**`tests/Feature/Marketplace/MarketplaceSearchApiTest.php`**
- No `topic_id`/`tab` returns all published, public ads, newest first — including both product and service ads.
- `products`/`services` tabs filter by `ad_type` exactly.
- `cosmetics`/`electronics`/`food` tabs correctly keyword-match against `category`/`title`/`short_description`/`content`/`tags`.
- Non-ad posts (`type = regular` / `event`), non-published, and non-public ads never appear.
- Blocked users (either direction) are excluded.
- `min_price`/`max_price` correctly narrow results.
- `max_distance` returns 422 when the caller has no `latitude`/`longitude`; otherwise correctly narrows to nearby ad posters.
- A custom topic's keyword matching correctly filters against `category`/`tags`/`title`.
- Pagination metadata is correct (`current_page`, `per_page`, `total`, `last_page`).

Run via `php artisan test --filter=Marketplace`, against the MySQL test database (matches this project's existing `phpunit.xml` configuration).

## Out of Scope

- Any changes to ad creation, editing, or the AI ad-creation conversation (`WorkspaceConversationService`).
- Favoriting/saving ads (no existing route wires up `SavedPost` for ads; out of scope here, same as it was left out of Matches Search Phase 1 for favorites-adjacent scope creep).
- A dedicated "Local" tab — distance is a filter (`max_distance`), not a tab, since only 5 fixed topics were specified.
- Extending `Post::PRODUCT_CATEGORIES`/`SERVICE_CATEGORIES` — cosmetics/electronics/food remain keyword-matched tabs, not new category enum values.
