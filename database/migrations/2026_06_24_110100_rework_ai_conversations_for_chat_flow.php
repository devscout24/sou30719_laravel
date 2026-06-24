<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn(['workspace', 'prompt', 'image_path', 'status', 'description']);
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->enum('status', ['idle', 'collecting', 'preview', 'awaiting_edit_instruction', 'published'])
                ->default('idle')
                ->after('workspace_id');
            $table->string('topic')->nullable()->after('status');
            $table->text('description')->nullable()->after('topic');
            $table->json('images')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
            $table->dropColumn(['status', 'topic', 'description', 'images']);
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->string('workspace')->default('social')->index();
            $table->text('prompt')->nullable();
            $table->string('description', 250)->nullable();
            $table->string('image_path')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
        });
    }
};
