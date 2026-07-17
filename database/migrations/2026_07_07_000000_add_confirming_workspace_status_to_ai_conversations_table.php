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
            $table->enum('status', ['idle', 'confirming_workspace', 'collecting', 'preview', 'awaiting_edit_instruction', 'published'])
                ->default('idle')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->enum('status', ['idle', 'collecting', 'preview', 'awaiting_edit_instruction', 'published'])
                ->default('idle')
                ->change();
        });
    }
};
