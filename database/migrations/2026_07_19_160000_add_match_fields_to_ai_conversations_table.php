<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * match_gender/match_criteria carry whatever the Matches workspace's
     * conversation-intent classifier already extracted from the user's message
     * (e.g. "I'm looking for a good height woman" -> gender=female,
     * criteria="good height"), so the flow doesn't have to ask "male or
     * female?" when it's already known. awaiting_match_criteria is a new
     * status for the one-round "could you be more specific?" follow-up when
     * criteria was stated but too vague to act on.
     */
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->string('match_gender')->nullable()->after('topic_clarify_attempts');
            $table->text('match_criteria')->nullable()->after('match_gender');
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->enum('status', [
                'idle',
                'confirming_workspace',
                'collecting',
                'preview',
                'awaiting_edit_instruction',
                'awaiting_match_gender',
                'awaiting_match_criteria',
                'completed',
                'published',
            ])->default('idle')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->enum('status', [
                'idle',
                'confirming_workspace',
                'collecting',
                'preview',
                'awaiting_edit_instruction',
                'awaiting_match_gender',
                'completed',
                'published',
            ])->default('idle')->change();
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn(['match_gender', 'match_criteria']);
        });
    }
};
