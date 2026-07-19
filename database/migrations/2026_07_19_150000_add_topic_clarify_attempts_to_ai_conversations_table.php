<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Counts consecutive unclear Phase-1 ("what would you like to post about?")
     * replies in the Social Post workspace's conversation flow, so the AI can
     * pivot to asking for a photo instead of repeating the question forever.
     * Reset to null the moment `topic` gets set.
     */
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->unsignedTinyInteger('topic_clarify_attempts')->nullable()->default(0)->after('topic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('topic_clarify_attempts');
        });
    }
};
