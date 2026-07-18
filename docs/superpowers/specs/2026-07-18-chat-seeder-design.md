# Chat Seeder Design

## Purpose
Give the frontend chat module realistic demo data so logging in as `user@user.com` shows a populated chat list and message history, instead of an empty state.

## Scope
- New `ChatSeeder` (`database/seeders/ChatSeeder.php`), registered in `DatabaseSeeder` after `PostSeeder`.
- 5 new fake users (`firstOrCreate` by email — idempotent, consistent with `UserSeeder`/`PostSeeder`), assigned the `user` role.
- For each new user: an accepted `ConnectionRequest` + `UserConnection` with `user@user.com`.
- For each connection: a private `Conversation` + two `ConversationParticipant` rows.
- 6–10 `Message`s per conversation, alternating senders, realistic dating-app small talk, timestamps spread over the last few days so `recent()`'s ordering (`messages_max_created_at`) looks natural. Last 1–2 messages from the other user left `is_read = false` to exercise the unread-count UI.

## Out of scope
- Message attachments, group conversations, conversations between the 5 seed users themselves — not needed for the stated goal (`user@user.com` demo login).

## Data model reference (existing, unchanged)
`ConnectionRequest` (accepted) → `UserConnection` → `Conversation` (`type: private`) → `ConversationParticipant` (x2) → `Message` (→ `MessageAttachment`, unused here).

## Idempotency
Every write uses `firstOrCreate`/existence checks keyed on natural identifiers (user email, connection user pair, conversation per connection) so re-running `db:seed` doesn't duplicate users, connections, or messages.
