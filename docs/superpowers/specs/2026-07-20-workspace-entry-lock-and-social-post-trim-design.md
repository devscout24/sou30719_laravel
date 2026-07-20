# Workspace Entry Lock & Social Post Flow Trim

## Purpose

The AI composition system is being redesigned around a hard rule: a user must pick one of the predefined workspace pills before any AI conversation happens — no free-text guessing at the entry point. Today, `WorkspaceConversationService::handleIdle()` does support pill clicks (deterministic exact-text match against `Workspace::prompt`), but it also falls back to `WorkspaceIntentClassifierService::interpret()`, an LLM call that guesses a workspace from arbitrary free text and drops the conversation into a `confirming_workspace` "did you mean X?" round-trip. That guessing path is being removed.

Separately, the Social Post workspace (`2026-07-19-social-post-conversation-design.md`) currently opens with a chat question — "What would you like to post about today?" — and only asks for a photo once a `topic` has been established through a multi-turn topic-classification loop. The product decision now is to skip that discovery chat entirely: enter the workspace and ask directly for a description + photo. The AI still generates the post's `topic` (used as the post's title) itself, from the description and image together, exactly as `PostCuratorService::curate()` already does — it's just no longer asked of the user as a separate conversational step first.

This spec is Phase 1 of a larger initiative (see conversation history — full redesign covers Social Post, Match Finder, and Marketplace workspaces, followed by a Phase 2 admin-configurability pass). This spec covers only the entry flow and the Social Post workspace. Match Finder and Marketplace are functionally unchanged and get their own specs later if they need work.

## Scope

- `AiConversation.status = idle` handling (all workspaces — this is the shared entry point).
- Social Post workspace collection (`Workspace::SLUG_SOCIAL_POST`, the non-marketplace branch of `WorkspaceConversationService::handleCollecting()`).

Out of scope:
- Match Finder workspace (gender/criteria flow) — unchanged, still reachable via pill click same as today.
- Marketplace workspace (ad form) — unchanged.
- Feed rendering, post types (`regular`/`ad`), admin approval — unchanged; `Post` already supports everything described for the feed.
- Any admin-panel configurability work (Phase 2 — separate spec later).
- Extracting a per-workspace strategy/interface pattern. The exploration for this spec found workspace *behavior* is hardcoded via `slug ===` branches inside `WorkspaceConversationService` rather than behind a shared interface, which will make Phase 2 harder. That refactor is deliberately deferred rather than smuggled into this change — Matches and Marketplace aren't changing here, so touching their code path now would be un-motivated churn.

## Entry flow (`idle` status)

`handleIdle(AiConversation $conversation, ?string $text)` becomes:

1. `matchWorkspaceExact($text)` — unchanged. This deterministic exact-string match against `Workspace::prompt` is what a pill click already does (client sends the pill's exact label as the message text). On match, `assignWorkspace()` is called directly — already skips any confirmation step today.
2. Anything else (blank text, or text that doesn't exactly match a pill) — store `MSG_SELECT_PROMPT` + the active pill list, same as the blank-text case today. No AI call. No error response — this covers stray free text from any client, old or new, gracefully.

Removed entirely:
- `WorkspaceIntentClassifierService` (`app/Services/AI/WorkspaceIntentClassifierService.php`) — its only caller was the guessing fallback being deleted. Its secondary responsibility (pre-extracting `match_gender`/`match_criteria` from the opening free-text message when it guessed Matches) also goes away — those fields are still populated the same way they are for a plain pill click today: via the `awaiting_match_gender` / `awaiting_match_criteria` steps inside the Matches workspace, unchanged.
- `confirming_workspace` status value, `handleConfirmingWorkspace()`, `confirmationPills()`, `PILL_CONFIRM_YES`, `PILL_CONFIRM_NO`, `MSG_CLARIFY_INTENT`, and the `'confirming_workspace' => ...` arm of the `handleMessage()` match statement.
- The `WorkspaceIntentClassifierService $classifier` constructor dependency on `WorkspaceConversationService`.

No API contract/shape change. `POST /conversations/{slug}/messages` and `GET /conversations/{slug}` are unchanged. The only client-observable behavior difference: sending non-matching free text while `status = idle` now always gets the same "select a workspace" pill re-prompt, instead of sometimes getting an LLM-guessed confirmation prompt.

## Social Post workspace collection

Replaces the two-phase topic-discovery-then-details flow from `2026-07-19-social-post-conversation-design.md` with a single collection step.

**On entering the workspace** (`assignWorkspace()` for `SLUG_SOCIAL_POST`): store one static opening line — *"Tell me about your post and share a photo to go with it."* (replaces the current `MSG_SOCIAL_OPENING`, "What would you like to post about today?"). No AI call, no change in intent-signaling — this workspace was already entered by an explicit pill click.

**Each subsequent turn** (`handleCollecting()`'s non-marketplace branch):
1. Merge any newly uploaded images into `conversation.images`.
2. If text is present, set `conversation.description` to it (overwrite, matching today's elaboration-storage behavior).
3. Validate what's present so far:
   - **Image**: at least one required. Missing → store the existing "please share a photo" prompt (reuse `MSG_STILL_NEED_IMAGE`), stop.
   - **Description**: required, non-blank, and at least a minimum length (deterministic check, no AI call — e.g. below ~3 words / 10 characters counts as insufficient). Missing/too short → store a new prompt asking for a bit more detail, stop.
4. Once both pass, call `PostCuratorService::curate($conversation->description, $conversation->images)` — unchanged, already derives `topic` (used as the post's title), polished `description`, `short_description`, and `tags` from description + image together.
5. On success: same `finalizePost()` path as today — persist the curated fields, status → `preview`, store the AI's short acknowledgment (`SocialPostCollectorService::acknowledge()`, unchanged), store the post preview card, store Approve/Edit/Delete pills. Unchanged from current behavior.
6. On `AIServiceException`: same as today — store `$e->getMessage()` as the reply, stay in `collecting`.

This means both an image *and* a validated-length description are required before curation — a stricter gate than today (today, an image alone is enough; text is optional elaboration). This matches the product requirement ("give me the description and the image... check if the description is correct, ask again if not").

**Removed as dead code:**
- `SocialPostCollectorService::classifyTopic()`, `askForDetails()`, `topicSystemPrompt()`, `detailsSystemPrompt()` — the topic-discovery chat questions, now fully replaced by direct curation. `acknowledge()` (and its system prompt) stays — still used at `finalizePost()`.
- `MSG_TOPIC_FALLBACK` (the 3-strikes "just share a photo" pivot message) and `topic_clarify_attempts` counter/column — the discovery loop they supported no longer exists.
- `MSG_SOCIAL_OPENING` replaced with the new opening line (same constant name, new value).

**New:**
- One deterministic description-length check (plain PHP, e.g. `str_word_count()` or `mb_strlen()` threshold — exact threshold is an implementation detail, not a product requirement) and one new reply constant for "please add a bit more detail."

## Migration

New migration on `ai_conversations`:
- Redefine `status` enum, dropping `confirming_workspace`: `idle, collecting, preview, awaiting_edit_instruction, awaiting_match_gender, awaiting_match_criteria, completed, published`.
- Drop `topic_clarify_attempts` column.

`match_gender` / `match_criteria` columns are unaffected — still used by the (unchanged) Matches workspace flow.

## Error handling

No new patterns. Reuses the two conventions already established in this codebase:
- `PostCuratorService::curate()` exceptions (`AIServiceException`) propagate to the `handleCollecting()` call site, which catches and stores `$e->getMessage()` as the AI's reply, staying in `collecting` — unchanged from today.
- The new description-length check is a plain validation, not an AI call — failure just re-prompts, no exception involved.

## Testing

No existing test coverage for the AI conversation system (`tests/Feature` currently has only Jetstream/Fortify auth boilerplate and unrelated feature tests). New `tests/Feature/AiConversation/` coverage added by this change:

- **Entry flow**: free text at `idle` (no exact pill match) → pills re-shown, conversation stays `idle`, no `WorkspaceIntentClassifierService` call (class is deleted, so this is structurally guaranteed rather than mocked). Exact pill-text match → `assignWorkspace()` called, `confirming_workspace` never entered (status enum no longer contains it, so this is also structurally guaranteed).
- **Social Post collection**: image only, no description → re-prompted for description, stays `collecting`. Description only, no image → re-prompted for image, stays `collecting`. Blank/short description + image → re-prompted for more detail. Valid description + image → `PostCuratorService::curate()` called once, conversation moves to `preview` with `topic`/`description`/`short_description`/`tags` populated, preview pills stored.
- **Regression smoke check**: Matches and Marketplace pill selections still route through `assignWorkspace()` into their existing (unchanged) handlers — no new tests needed beyond confirming they're unaffected by the `idle`/Social-Post changes.

## Out of scope

- Match Finder workspace — unchanged.
- Marketplace workspace — unchanged.
- Feed rendering / two post types (`regular`/`ad`) / admin approval — already implemented, unchanged.
- Admin-panel configurability (Phase 2) — separate spec, later.
- Per-workspace strategy/interface refactor — deferred; noted as a future need, not undertaken here.
