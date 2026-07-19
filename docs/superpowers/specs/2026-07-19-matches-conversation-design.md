# Matches AI Conversation Redesign

## Purpose
Third piece of the client's AI-conversation correction request (after Predefined Prompts and the Social Post rewrite, both done). Today, `WorkspaceConversationService::enterMatchesWorkspace()` always asks "What are you looking for — male or female?" from scratch and only ever filters candidates by gender — even when the user already stated a gender and/or a preference (e.g. "I'm looking for a good height woman") in the very message that got them into this workspace, or already has a saved `DatingPreference`. Client wants: if the user's intent was generic ("match me", a bare workspace-card tap), use their saved dating preference; if they stated something specific but underspecified (e.g. "good height"), ask them to be specific; once specific, actually filter/rank candidates by that criterion, not just gender.

## Scope
Matches workspace only (`Workspace::SLUG_MATCHES`). No changes to Social Post, Market Place, or the confirmation/pill mechanics of `handleIdle()`/`handleConfirmingWorkspace()` beyond what's needed to carry criteria through them. Out of scope: a UI for editing `DatingPreference` (already exists via `/matching-criteria` endpoints, unchanged), any change to `MatchRecommendation`'s schema (its existing `compatibility_score`/`reason` columns are reused, not extended), building a general-purpose structured attribute system (age range, body type, etc. as separate typed columns) — height and any other attribute the user mentions are handled as free text, semantically, not via new typed columns.

## Data model reuse
Small migration: two new nullable `ai_conversations` columns, `match_gender` (nullable string: `male`/`female`/`both`) and `match_criteria` (nullable text, free-text attribute description, e.g. "good height"). Deliberately not reusing `topic`/`description` (already dual-purposed between Social Post and Market Place) — a third, semantically-unrelated meaning on the same columns would be confusing for a future reader. Same low-risk pattern as Task 1's `topic_clarify_attempts` column.

## Conversation flow

### Entering the workspace — no new parameter needed
The classifier path already has the richer original text (e.g. "I'm looking for a good height woman") in scope one step before Matches is even confirmed: `handleIdle()` calls `WorkspaceIntentClassifierService::interpret($text, ...)`, and *that same call* — not a later step — is extended (below) to also extract gender/criteria when it decides Matches fits. `handleIdle()` persists `match_gender`/`match_criteria` onto the conversation in the same `update()` call that already sets `workspace_id` and `status: confirming_workspace` — before the user has even said "yes". If the user later declines ("no" in `handleConfirmingWorkspace()`), reset `match_gender`/`match_criteria` to null alongside the existing `workspace_id` reset, since they're now stale.

For the exact-pill-match path (`matchWorkspaceExact($text)`, e.g. the user taps the "Find a match" pill verbatim) — the classifier is never invoked, so `match_gender`/`match_criteria` are simply never populated. This is the desired outcome: a bare pill tap conveys no real criteria, so `enterMatchesWorkspace()` correctly falls through to the "no gender captured — fall back to stored preference" branch below. No special-casing needed for this path.

By the time `assignWorkspace()` → `enterMatchesWorkspace()` runs (whichever path led there), `match_gender`/`match_criteria` are already whatever they're going to be — `enterMatchesWorkspace()` just reads them off `$conversation`. `assignWorkspace()`'s signature is unchanged.

### `WorkspaceIntentClassifierService::interpret()` extension
Return shape grows from `{workspace, reply}` to `{workspace, reply, match_gender, match_criteria}`. The two new fields are only ever populated when `workspace` resolves to Matches; `null` otherwise. The system prompt gains an instruction: when the message points to the Matches workspace, also extract a `gender` (`male`/`female`/`both`/`null` if not stated) and a `criteria` (free text describing any other stated preference, e.g. "good height"; `null` if nothing beyond gender/generic intent was said).

### `enterMatchesWorkspace()` (replaces current unconditional gender-ask)
1. If `hasCompletedDatingProfile()` fails, unchanged: ask to complete profile, return to idle.
2. Resolve gender:
   - If `$conversation->match_gender` is set (extracted from the triggering message), use it.
   - Else if the triggering text was generic/vague (exact pill match, or classifier extracted no gender) — fall back to `$user->datingPreference->interested_in`.
   - Else (shouldn't normally happen given the above two cover both cases) fall through to asking, same as today.
3. If gender is still unresolved after both, ask the existing `MSG_ASK_GENDER` question with gender pills (today's behavior, unchanged as the fallback).
4. If gender is resolved but `$conversation->match_criteria` is set and is judged too vague to act on (a new lightweight classifier call — see below), ask a natural clarifying question about that specific criterion (new status `awaiting_match_criteria`) instead of proceeding straight to search.
5. Once gender is resolved and criteria (if any) is either absent or concrete, search and present matches (see below).

### New status: `awaiting_match_criteria`
Added to the `status` enum (new migration alongside the two new columns). Handles one round of "be more specific" — reuses the same 3-strike-cap pattern as Social Post's topic discovery isn't necessary here (this is a single follow-up question, not an open-ended topic hunt): if the clarifying reply is still not concrete enough, proceed with the vague criteria as-is rather than looping — matching-by-criteria is a "nice ranking boost," not a hard gate the way an image is for Social Post, so failing to get a crisp answer should degrade to "search by gender, ignore criteria" rather than stall the conversation.

### New service: `App\Services\AI\MatchCriteriaService`
- `isConcrete(string $criteria): bool` — one AI call: is this specific enough to act on (e.g. "5'6\" or taller" = concrete; "good height" = not concrete)?
- `rankCandidates(string $criteria, Collection $candidates): array<int, array{user_id: int, score: int, reason: string}>` — one AI call per search (not per candidate): given the free-text criteria and a bounded list (already gender-filtered, `limit(10)` as today) of candidates' relevant profile fields (`height`, `about`/`about_me`, `hobbies`), returns each candidate's fit as a 0-100 `compatibility_score` and a short `reason` — both already have a home in the existing `MatchRecommendation` schema (`compatibility_score`, `reason` columns, currently always written empty/default). When there's no criteria at all (gender-only match, today's existing behavior), skip this call entirely — `compatibility_score`/`reason` stay as today (unset), no behavior change for the plain "just find me matches" path.

### `findMatchCandidates()`
One small change: gender can now resolve to `'both'` (from `DatingPreference->interested_in`, which allows `'both'` per its enum, or a message like "match with him or her") where previously this method only ever received `'male'`/`'female'` from the pill-based `classifyGender()`. When `$gender === 'both'`, skip the `dating_gender` equality filter entirely (match either); otherwise filter as today. `limit(10)`, active+completed profile requirements unchanged. Criteria-based ranking happens as a second pass over this method's result, not by changing its SQL filter — free-text criteria can't be expressed as a reliable WHERE clause given `height` has no fixed format.

### `storeMatchSuggestions()` payload
Currently sends only `user_id`/`name`/`username`/`city`/`about`/`photo` to the frontend. Since `compatibility_score`/`reason` are computed for the sole purpose of explaining *why* a candidate was suggested, they're added to each candidate's entry in this payload too — otherwise the ranking work would be invisible to the user it's meant to help. `null` for both when no criteria was given (unchanged, gender-only path).

## Error handling
Same conventions as Social Post: `MatchCriteriaService`'s two methods catch `AIServiceException` internally and degrade gracefully (`isConcrete` failure → treat as concrete, proceed rather than block; `rankCandidates` failure → fall back to today's unranked candidate list, `compatibility_score`/`reason` left unset) — a transient AI outage must never block a user from getting match suggestions entirely.

## Out of scope
- Social Post, Market Place — untouched.
- Any structured (typed, queryable) height/age/etc. columns — everything beyond gender stays free text, AI-interpreted.
- Editing existing `MatchRecommendation` rows when a user re-runs a search — each search still creates/updates via `updateOrCreate` as today, just with `compatibility_score`/`reason` populated when criteria was given.
