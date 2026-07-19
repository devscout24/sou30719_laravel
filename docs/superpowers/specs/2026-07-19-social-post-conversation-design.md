# Social Post AI Conversation Redesign

## Purpose
The "Predefined Prompt" client complaint spans several sub-projects (see `2026-07-19-predefined-prompts-correction*` work, already done). This spec covers the second piece: making the Social Post workspace's AI conversation (`WorkspaceConversationService::handleCollecting()`) feel like a natural chat instead of a rigid two-field form. Today it only checks `hasDescription()` / `hasImages()` and fires one of three canned strings (`MSG_NEED_DESCRIPTION` / `MSG_NEED_IMAGES` / `MSG_NEED_BOTH`) regardless of what the user actually said — "Hey" gets the same reply as a real answer. Client wants the AI to understand the topic first, then converse naturally to gather enough to write a good post.

## Scope
Social Post workspace only (`Workspace::SLUG_SOCIAL_POST`). This is the only workspace that currently reaches the generic (non-marketplace) branch of `handleCollecting()` — Event/Interest Hub/Personal Courier are `is_supported = false` and never get there; Market Place has its own `handleMarketplaceCollecting()` branch, covered separately (Ads spec, not this one). Matches' conversational criteria-parsing is also a separate spec.

Out of scope: building a shared/generalized "AI slot-filler" service reusable across Social Post and the future Matches redesign. Matches' needs (structured gender/height criteria) aren't defined yet; extracting an abstraction now risks guessing its shape wrong. Revisit after Matches is designed.

## Conversation model
Two phases within the existing `collecting` status — no new status enum value. The phase is discriminated by whether `AiConversation::topic` is set yet (reusing the existing column, not adding a new one):

### Phase 1 — topic unknown (`topic IS NULL`)
On entering the Social Post workspace, store a static opening line (replaces `MSG_SOCIAL_GUIDANCE` for this workspace): `"Hi! What would you like to post about today?"` No AI call — there's no conversation context yet to react to.

Each subsequent user reply in this phase is sent to a new classifier, `SocialPostCollectorService::classifyTopic(string $message, array $history)`, returning `{topic: ?string, reply: ?string}`:
- If the message clearly states something postable (e.g. "food", "my trip to Bali"), `topic` is a short 1-4 word label, `reply` is null. If the same message also included an image, that satisfies Phase 2's only requirement too — skip straight to "Ready" in this same turn. Otherwise move to Phase 2 (ask for details next).
- If vague ("hey", "idk", "post"), `topic` is null and `reply` is a natural, context-aware follow-up question (not a canned string).
- If the message includes an uploaded image and no clear topic yet, the classifier is skipped — go straight to vision-based topic detection (see "Image-first" below), which also lands on "Ready" directly since the image is already present.

**Unclear-reply cap:** `ai_conversations.topic_clarify_attempts` (new nullable int column, default 0) increments each time Phase 1 asks again. On the 3rd consecutive unclear reply, stop asking in words and switch to: `"No worries — just share a photo of what you'd like to post, and I'll take it from there."` This still counts as a Phase 1 reply (topic remains null) but changes tactics rather than repeating the same question a 4th time.

**Image-first path:** if the user attaches an image before a topic is established (either as their very first Phase 1 reply, or after the 3-strike pivot), skip the topic classifier and call `PostCuratorService::curate('', $conversation->images)` directly — an empty description string is fine since `curate()`'s prompt already leans on the image(s) when text is thin. Use the returned `topic` to populate `AiConversation::topic`, then go straight to "Ready" (an image already exists, so Phase 2 has nothing left to ask). Store an acknowledgment message first (see "Acknowledgment" below) before the preview card, matching the client's example ("The image is about banana, I am creating post...").

### Phase 2 — topic known, image still missing (`topic IS NOT NULL`, no images yet)
On the turn `topic` first gets set (and no image arrived in that same turn), store one AI-generated message via `SocialPostCollectorService::askForDetails(string $topic, array $history)` — references the known topic, asks for optional elaboration and a required image, in the classifier's own words (not a fixed template). Example shape, not literal output: *"Do you have any recommendation which post, also can you share the image?"*

Subsequent turns: any text is stored as `description` (elaboration, optional — does not block progress); any uploaded image satisfies the requirement. Once at least one image exists, move to "Ready".

Image remains a hard requirement to finish a Social Post — this does not change the existing product rule ("attach image(s) to proceed"). What changes is that a text description is no longer separately required once an image is present; the topic + image (with vision filling in gaps) is enough.

### Ready (topic + ≥1 image present)
Call `PostCuratorService::curate($conversation->description ?: $conversation->topic, $conversation->images)` — falls back to the bare topic as the description when the user never added elaboration text, letting vision carry the content (the banana example).

Before showing the structured preview card (`storePostPreview()`, unchanged), store one short AI acknowledgment message narrating what it understood, generated from the curate() result — e.g. *"Got it — looks like a post about bananas! Here's what I put together:"*. This directly addresses "no robotic answer": the transition from chat to preview card is narrated, not a silent card-dump.

## New/changed components

**Migration**: add `topic_clarify_attempts` (nullable `unsignedTinyInteger`, default 0) to `ai_conversations`. Reset to `null` the moment `topic` is set (no further use needed once Phase 1 ends).

**New service** `App\Services\AI\SocialPostCollectorService` (same pattern as `WorkspaceIntentClassifierService`/`ReplyIntentClassifierService` — constructor-injected `OpenAIService`, one `chat()` call per method, `jsonMode: true`, strict-JSON system prompts, safe fallback reply on `AIServiceException`):
- `classifyTopic(string $message, array $history): array{topic: ?string, reply: ?string}`
- `askForDetails(string $topic, array $history): string`
- `acknowledge(string $topic, string $shortDescription): string` — short narration before the preview card.

**Rewritten** `WorkspaceConversationService::handleCollecting()`'s non-marketplace branch (currently lines ~219–274) replaces the `hasDescription()`/`hasImages()` boolean gates with the phase logic above. `handleMarketplaceCollecting()` is untouched.

**Constants replaced**: `MSG_SOCIAL_GUIDANCE`, `MSG_NEED_DESCRIPTION`, `MSG_NEED_IMAGES`, `MSG_NEED_BOTH` are dropped for the Social Post path (Market Place keeps its own unrelated constants). New static opening line added.

## Error handling
Two existing conventions in this codebase, both reused as-is (no new pattern introduced):
- `PostCuratorService::curate()` calls (in the "Ready" / image-first curation paths) let `AIServiceException` propagate; `WorkspaceConversationService` catches it at the call site and stores `$e->getMessage()` as the AI's reply — exactly how `handleCollecting()` already surfaces curator failures today.
- `SocialPostCollectorService`'s three methods follow the same convention as their siblings `WorkspaceIntentClassifierService`/`ReplyIntentClassifierService`: catch `AIServiceException` internally, log a warning, and return a safe static fallback string — so a transient AI outage during topic clarification degrades to a generic follow-up question rather than surfacing a raw service-error message on every vague reply.

## Out of scope
- Event, Interest Hub, Personal Courier — unsupported, never reach this code path.
- Market Place ad collection — separate spec.
- Matches conversational criteria — separate spec.
- Any change to the preview/approve/edit/delete pill flow after the card is shown.
