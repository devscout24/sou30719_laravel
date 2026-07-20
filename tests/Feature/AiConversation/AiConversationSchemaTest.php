<?php

namespace Tests\Feature\AiConversation;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AiConversationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_topic_clarify_attempts_column_is_removed(): void
    {
        $this->assertFalse(Schema::hasColumn('ai_conversations', 'topic_clarify_attempts'));
    }

    public function test_confirming_workspace_status_is_no_longer_a_valid_enum_value(): void
    {
        $user = User::factory()->create();
        $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'idle']);

        $this->expectException(QueryException::class);

        $conversation->update(['status' => 'confirming_workspace']);
    }
}
