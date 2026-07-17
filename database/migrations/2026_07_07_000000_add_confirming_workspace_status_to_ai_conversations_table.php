<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE ai_conversations MODIFY status ENUM('idle', 'confirming_workspace', 'collecting', 'preview', 'awaiting_edit_instruction', 'published') NOT NULL DEFAULT 'idle'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE ai_conversations MODIFY status ENUM('idle', 'collecting', 'preview', 'awaiting_edit_instruction', 'published') NOT NULL DEFAULT 'idle'");
    }
};
