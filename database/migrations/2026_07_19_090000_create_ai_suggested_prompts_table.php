<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_suggested_prompts', function (Blueprint $table) {
            $table->id();

            // Which AI chat surface this suggestion belongs to: feed_search
            // (/feed/ai-chat) or workspace_conversation
            // (the /conversations post/ad/event creation flow).
            $table->string('context');

            // Optional: scope a workspace_conversation prompt to one workspace
            // (e.g. Market Place vs Event have different natural example prompts).
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();

            // Short chip label shown on the button; falls back to `prompt` on the frontend if blank.
            $table->string('label', 150)->nullable();

            // The actual text sent as the user's message when the chip is tapped.
            $table->string('prompt', 500);

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['context', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_suggested_prompts');
    }
};
