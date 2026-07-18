# Chat Seeder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `ChatSeeder` that gives `user@user.com` five connected contacts and realistic message history, so the chat module has demo data on a fresh seed.

**Architecture:** One new seeder class (`database/seeders/ChatSeeder.php`) that, per contact: `firstOrCreate`s the user, creates an accepted `ConnectionRequest` + `UserConnection` between them and `user@user.com`, creates the `Conversation` + `ConversationParticipant` rows, then creates `Message` rows with backdated `created_at` timestamps. Registered in `DatabaseSeeder` after `PostSeeder`. Everything is keyed off natural identifiers (email, user-pair, connection) so re-running `db:seed` is a no-op on second pass.

**Tech Stack:** Laravel 11 seeders/Eloquent, existing models `User`, `ConnectionRequest`, `UserConnection`, `Conversation`, `ConversationParticipant`, `Message` (all already defined, no schema changes).

## Global Constraints
- Idempotent: every seeder in this project uses `firstOrCreate` / existence checks (see `UserSeeder`, `PostSeeder`) — this seeder must follow the same pattern so `php artisan db:seed` is safe to re-run.
- No schema changes — all tables (`users`, `connection_requests`, `user_connections`, `conversations`, `conversation_participants`, `messages`) already exist and are unchanged.
- No test framework covers seeders in this repo (no `tests/**/Seeder*`) — verification is done by running the seeder against the local dev DB and inspecting row counts via `artisan tinker`, matching how `PostSeeder`/`UserSeeder` are verified.
- Password for seeded users: `Hash::make('12345678')`, matching `UserSeeder`'s convention.

---

### Task 1: Create `ChatSeeder`

**Files:**
- Create: `database/seeders/ChatSeeder.php`

**Interfaces:**
- Consumes: `App\Models\User` (`email`, `id`), `App\Models\ConnectionRequest` (`sender_id`, `receiver_id`, `status`), `App\Models\UserConnection` (`user_one_id`, `user_two_id`, `connection_request_id`, `connected_at`, `->conversation()`), `App\Models\Conversation` (`connection_id`, `type`, `->participants()`, `->messages()`), `App\Models\ConversationParticipant` (`conversation_id`, `user_id`, `joined_at`), `App\Models\Message` (`sender_id`, `message_type`, `message`, `is_read`, `created_at`, `updated_at`).
- Produces: `Database\Seeders\ChatSeeder` class with a public `run(): void`, consumed by `DatabaseSeeder` in Task 2.

- [ ] **Step 1: Write the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\ConnectionRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Models\UserConnection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $me = User::where('email', 'user@user.com')->first();

        if (!$me) {
            $this->command?->warn('ChatSeeder: user@user.com not found, skipping. Run UserSeeder first.');
            return;
        }

        $guardName = config('auth.defaults.guard', 'web');
        Role::firstOrCreate(['name' => 'user', 'guard_name' => $guardName]);

        foreach ($this->contacts() as $data) {
            $contact = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'username'          => $data['username'],
                    'password'          => Hash::make('12345678'),
                    'status'            => 'active',
                    'gender'            => $data['gender'],
                    'email_verified_at' => now(),
                ]
            );
            $contact->assignRole('user');

            $connection   = $this->connectionBetween($me, $contact);
            $conversation = $this->conversationFor($connection);

            $this->seedMessages($conversation, $me, $contact, $data['messages']);
        }
    }

    private function connectionBetween(User $me, User $contact): UserConnection
    {
        [$oneId, $twoId] = $me->id < $contact->id ? [$me->id, $contact->id] : [$contact->id, $me->id];

        $connection = UserConnection::where('user_one_id', $oneId)->where('user_two_id', $twoId)->first();

        if ($connection) {
            return $connection;
        }

        $request = ConnectionRequest::firstOrCreate(
            ['sender_id' => $me->id, 'receiver_id' => $contact->id],
            ['status' => 'accepted']
        );

        return UserConnection::create([
            'user_one_id'           => $oneId,
            'user_two_id'           => $twoId,
            'connection_request_id' => $request->id,
            'connected_at'          => now(),
        ]);
    }

    private function conversationFor(UserConnection $connection): Conversation
    {
        $conversation = $connection->conversation()->first();

        if ($conversation) {
            return $conversation;
        }

        $conversation = Conversation::create([
            'connection_id' => $connection->id,
            'type'          => 'private',
        ]);

        $conversation->participants()->createMany([
            ['user_id' => $connection->user_one_id, 'joined_at' => now()],
            ['user_id' => $connection->user_two_id, 'joined_at' => now()],
        ]);

        return $conversation;
    }

    private function seedMessages(Conversation $conversation, User $me, User $contact, array $messages): void
    {
        if ($conversation->messages()->exists()) {
            return;
        }

        $lastIndex = count($messages) - 1;

        foreach ($messages as $index => $entry) {
            $isFromContact = $entry['from'] === 'contact';
            $sender        = $isFromContact ? $contact : $me;
            $createdAt     = now()->subMinutes($entry['minutes_ago']);

            $conversation->messages()->create([
                'sender_id'    => $sender->id,
                'message_type' => 'text',
                'message'      => $entry['text'],
                'is_read'      => !($isFromContact && $index === $lastIndex),
                'created_at'   => $createdAt,
                'updated_at'   => $createdAt,
            ]);
        }

        $conversation->touch();
    }

    protected function contacts(): array
    {
        return [
            [
                'name'     => 'Sophia Bennett',
                'username' => 'sophia_b',
                'email'    => 'sophia.bennett@example.com',
                'gender'   => 'female',
                'messages' => [
                    ['from' => 'contact', 'text' => 'Hey! I saw we matched on the hiking interest 🙂', 'minutes_ago' => 4320],
                    ['from' => 'me', 'text' => 'Hi Sophia! Yes, I try to get out on trails most weekends.', 'minutes_ago' => 4310],
                    ['from' => 'contact', 'text' => 'Same here! Have you done the Ridgeline trail yet?', 'minutes_ago' => 4300],
                    ['from' => 'me', 'text' => "Not yet, heard it's tough but the view is worth it.", 'minutes_ago' => 2900],
                    ['from' => 'contact', 'text' => 'It really is. We should go together sometime!', 'minutes_ago' => 2880],
                    ['from' => 'me', 'text' => 'I\'d like that. Are you free this weekend?', 'minutes_ago' => 130],
                    ['from' => 'contact', 'text' => 'Saturday morning works for me, what time were you thinking?', 'minutes_ago' => 20],
                ],
            ],
            [
                'name'     => 'Liam Carter',
                'username' => 'liam_c',
                'email'    => 'liam.carter@example.com',
                'gender'   => 'male',
                'messages' => [
                    ['from' => 'contact', 'text' => 'Hey, thanks for the connect! Loved your bio, especially the coffee snob line ☕', 'minutes_ago' => 10080],
                    ['from' => 'me', 'text' => "Ha, guilty as charged. What's your go-to order?", 'minutes_ago' => 10070],
                    ['from' => 'contact', 'text' => 'Flat white, always. Judge me.', 'minutes_ago' => 10060],
                    ['from' => 'me', 'text' => 'No judgment, solid choice.', 'minutes_ago' => 8000],
                    ['from' => 'contact', 'text' => "There's a new place downtown, wanna check it out sometime?", 'minutes_ago' => 7990],
                    ['from' => 'me', 'text' => "Sure, I'm free most evenings this week.", 'minutes_ago' => 300],
                    ['from' => 'contact', 'text' => 'Great, how about Thursday around 7?', 'minutes_ago' => 15],
                ],
            ],
            [
                'name'     => 'Olivia Grant',
                'username' => 'olivia_g',
                'email'    => 'olivia.grant@example.com',
                'gender'   => 'female',
                'messages' => [
                    ['from' => 'contact', 'text' => "Hi! Saw you're into board games too, nice.", 'minutes_ago' => 5760],
                    ['from' => 'me', 'text' => 'Yes! Catan is my most-played by far.', 'minutes_ago' => 5750],
                    ['from' => 'contact', 'text' => 'Ooh a classic. Do you go to the Thursday board game nights?', 'minutes_ago' => 5740],
                    ['from' => 'me', 'text' => "Not yet, but I've been meaning to check it out.", 'minutes_ago' => 4000],
                    ['from' => 'contact', 'text' => "You should! This week's game night has a Catan table set up.", 'minutes_ago' => 3990],
                    ['from' => 'me', 'text' => "That settles it, I'm coming this week.", 'minutes_ago' => 200],
                    ['from' => 'contact', 'text' => "Awesome, I'll save you a seat!", 'minutes_ago' => 25],
                ],
            ],
            [
                'name'     => 'Noah Foster',
                'username' => 'noah_f',
                'email'    => 'noah.foster@example.com',
                'gender'   => 'male',
                'messages' => [
                    ['from' => 'contact', 'text' => "Hey, saw you're training for a marathon too. How's it going?", 'minutes_ago' => 2880],
                    ['from' => 'me', 'text' => 'Slow but steady! Just hit 15 miles on my long run.', 'minutes_ago' => 2870],
                    ['from' => 'contact', 'text' => "Nice pace! I'm building up to my first half this fall.", 'minutes_ago' => 2860],
                    ['from' => 'me', 'text' => "That's exciting, first half is a great milestone.", 'minutes_ago' => 1400],
                    ['from' => 'contact', 'text' => 'Thanks! Might need a running buddy for the early mornings.', 'minutes_ago' => 1390],
                    ['from' => 'me', 'text' => "I'm usually out around 6am if that works.", 'minutes_ago' => 90],
                    ['from' => 'contact', 'text' => "Perfect, let's sync up for this weekend's run.", 'minutes_ago' => 10],
                ],
            ],
            [
                'name'     => 'Ava Mitchell',
                'username' => 'ava_m',
                'email'    => 'ava.mitchell@example.com',
                'gender'   => 'female',
                'messages' => [
                    ['from' => 'contact', 'text' => 'Hi! Your profile made me smile, the travel photos are great.', 'minutes_ago' => 1440],
                    ['from' => 'me', 'text' => "Thank you! That one's from a trip to Portugal last year.", 'minutes_ago' => 1430],
                    ['from' => 'contact', 'text' => "I've always wanted to go! Any recommendations?", 'minutes_ago' => 1420],
                    ['from' => 'me', 'text' => 'Definitely visit Porto, the food alone is worth the trip.', 'minutes_ago' => 700],
                    ['from' => 'contact', 'text' => "Adding it to my list! What's next on your travel list?", 'minutes_ago' => 690],
                    ['from' => 'me', 'text' => 'Thinking Japan next spring, still planning it out.', 'minutes_ago' => 45],
                    ['from' => 'contact', 'text' => 'That sounds amazing, keep me posted!', 'minutes_ago' => 5],
                ],
            ],
        ];
    }
}
```

- [ ] **Step 2: Verify syntax**

Run: `php -l database/seeders/ChatSeeder.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add database/seeders/ChatSeeder.php
git commit -m "feat: Add ChatSeeder with demo contacts and conversations for user@user.com"
```

---

### Task 2: Register in `DatabaseSeeder` and verify end-to-end

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes: `Database\Seeders\ChatSeeder` from Task 1.
- Produces: nothing further downstream — this is the last task.

- [ ] **Step 1: Register the seeder**

In `database/seeders/DatabaseSeeder.php`, add the import and call, after `PostSeeder`:

```php
use Database\Seeders\PostSeeder;
use Database\Seeders\ChatSeeder;
```

```php
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            NotificationSeeder::class,
            CompanySettingsSeeder::class,
            DynamicPageSeeder::class,
            WorkspaceSeeder::class,
            PostSeeder::class,
            ChatSeeder::class,
        ]);
```

- [ ] **Step 2: Run the full seed against the local dev database**

Run: `php artisan db:seed`
Expected: Command completes with no errors; output includes no exceptions from `ChatSeeder`.

- [ ] **Step 3: Verify the data via tinker**

Run:
```bash
php artisan tinker --execute="
\$me = App\Models\User::where('email','user@user.com')->first();
echo 'contacts: ' . App\Models\User::whereIn('email', ['sophia.bennett@example.com','liam.carter@example.com','olivia.grant@example.com','noah.foster@example.com','ava.mitchell@example.com'])->count() . PHP_EOL;
echo 'connections: ' . App\Models\UserConnection::where('user_one_id', \$me->id)->orWhere('user_two_id', \$me->id)->count() . PHP_EOL;
echo 'conversations: ' . App\Models\Conversation::whereHas('participants', fn(\$q) => \$q->where('user_id', \$me->id))->count() . PHP_EOL;
echo 'messages: ' . App\Models\Message::whereHas('conversation.participants', fn(\$q) => \$q->where('user_id', \$me->id))->count() . PHP_EOL;
"
```
Expected: `contacts: 5`, `connections: 5`, `conversations: 5`, `messages: 35`.

- [ ] **Step 4: Verify idempotency by re-running the seeder**

Run: `php artisan db:seed --class=ChatSeeder`

Then re-run the same tinker snippet from Step 3.
Expected: Identical counts (`contacts: 5`, `connections: 5`, `conversations: 5`, `messages: 35`) — no duplicates created on second run.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/DatabaseSeeder.php
git commit -m "feat: Register ChatSeeder in DatabaseSeeder"
```
