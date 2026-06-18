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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['regular', 'event', 'ad'])->default('regular');
            $table->enum('created_by', ['user', 'ai'])->default('user');

            $table->string('title')->nullable(); // for event & ad posts
            $table->text('content')->nullable();

            // Ad type fields
            $table->decimal('price', 10, 2)->nullable();
            $table->string('location')->nullable();
            $table->string('category')->nullable();

            // Event type fields
            $table->timestamp('event_date')->nullable();
            $table->string('event_location')->nullable();

            $table->enum('visibility', ['public', 'friends', 'private'])->default('public');
            $table->enum('status', ['published', 'draft', 'removed'])->default('published');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
