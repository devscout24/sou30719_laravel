<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free-text workspace guessing (and its "did you mean X?" confirmation
     * round-trip) was removed — workspace selection is pill-only now, so
     * confirming_workspace is unreachable. Social Post's topic-discovery chat
     * step was also removed (topic is now always AI-generated from the
     * description+image at curation time), so its retry counter goes too.
     */
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('topic_clarify_attempts');
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->enum('status', [
                'idle',
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
                'awaiting_match_criteria',
                'completed',
                'published',
            ])->default('idle')->change();
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->unsignedTinyInteger('topic_clarify_attempts')->nullable()->default(0)->after('topic');
        });
    }
};
