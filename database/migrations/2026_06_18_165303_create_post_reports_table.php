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
        Schema::create('post_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('reason', [
                'spam',
                'inappropriate',
                'harassment',
                'false_information',
                'other'
            ]);
            $table->text('description')->nullable(); // extra detail if reason is 'other'

            $table->enum('status', ['pending', 'reviewed', 'dismissed'])->default('pending'); // for admin panel

            $table->unique(['post_id', 'user_id']); // user can only report a post once

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_reports');
    }
};
