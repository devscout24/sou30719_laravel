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
        Schema::create('match_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recommended_user_id')->constrained('users')->cascadeOnDelete();

            $table->integer('compatibility_score')->default(0); // 0-100
            $table->text('reason')->nullable();

            $table->enum('status', ['pending', 'viewed', 'dismissed'])->default('pending');

            $table->unique(['user_id', 'recommended_user_id']); // no duplicate recommendations

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_recommendations');
    }
};
