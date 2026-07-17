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
                'long_term',
                'marriage',
                'friendship',
                'not_sure'
            ])->nullable();

            $table->enum('looking_for', ['male', 'female', 'both'])->nullable();

            $table->string('height')->nullable();

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

            // Profile set-up
            $table->string('nickname')->nullable();
            $table->boolean('showcase_page')->default(false);
            $table->string('city')->nullable();
            $table->text('about')->nullable(); // "About You and Your Expectations"
            $table->string('profile_setup_media')->nullable();

            // Identity & Location (dating-specific identity, separate from account identity)
            $table->string('dating_nickname')->nullable();
            $table->date('dating_dob')->nullable();
            $table->string('dating_full_name')->nullable();
            $table->enum('relationship_status', ['single', 'married', 'divorced', 'separated', 'widowed'])->nullable();
            $table->enum('dating_gender', ['male', 'female'])->nullable();
            $table->string('dating_email')->nullable();
            $table->string('dating_location')->nullable();
            $table->string('dating_country')->nullable();
            $table->string('address_1')->nullable();
            $table->string('address_2')->nullable();
            $table->boolean('connections_view')->default(true); // visibility toggle

            // Appearance & Lifestyle
            $table->enum('lifestyle_habits', ['active', 'moderate', 'sedentary'])->nullable();
            $table->string('body_type')->nullable();
            $table->string('ethnicity')->nullable();
            $table->string('religious_beliefs')->nullable();
            $table->json('languages')->nullable();

            // Interests & Personality
            $table->json('hobbies')->nullable();
            $table->json('personality_traits')->nullable();
            $table->enum('pet_preference', ['loves_pets', 'no_pets', 'allergic', 'has_pets'])->nullable();
            $table->string('political_views')->nullable();
            $table->enum('family_plans', ['want_kids', 'open_to_kids', 'dont_want_kids', 'not_sure'])->nullable();
            $table->enum('children_status', ['no_kids', 'has_kids'])->nullable();
            $table->string('prompt_question')->nullable();
            $table->text('prompt_answer')->nullable();

            // Visual info
            $table->text('visual_description')->nullable();

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
