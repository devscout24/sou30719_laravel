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
        Schema::create('dating_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();

            $table->enum('interested_in', ['male', 'female', 'both'])->nullable();

            $table->integer('min_age')->default(18);
            $table->integer('max_age')->default(50);

            $table->integer('max_distance')->default(50); // in km

            $table->enum('relationship_goal', [
                'casual',
                'long_term',
                'marriage',
                'friendship',
                'not_sure'
            ])->nullable();

            // Matching criteria
            $table->string('deal_breakers')->nullable();
            $table->text('partner_preferences')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dating_preferences');
    }
};
