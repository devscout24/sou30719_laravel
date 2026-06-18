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
        Schema::create('dating_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();

            $table->text('about_me')->nullable();
            $table->string('occupation')->nullable();
            $table->string('education')->nullable();

            $table->enum('relationship_goal', [
                'casual',
                'serious',
                'friendship',
                'marriage',
                'not_sure'
            ])->nullable();

            $table->enum('looking_for', ['male', 'female', 'both'])->nullable();

            $table->integer('height')->nullable(); // in cm

            $table->enum('religion', [
                'muslim',
                'christian',
                'hindu',
                'buddhist',
                'other',
                'prefer_not_to_say'
            ])->nullable();

            $table->enum('smoking', ['yes', 'no', 'occasionally'])->nullable();
            $table->enum('drinking', ['yes', 'no', 'occasionally'])->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dating_profiles');
    }
};
