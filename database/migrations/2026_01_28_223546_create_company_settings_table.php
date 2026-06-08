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
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();

            // Company Info
            $table->string('company_name')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('hotline')->nullable();
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            $table->string('logo')->default('logo.png');

            // App Links
            $table->string('play_store_link')->nullable();
            $table->string('apple_store_link')->nullable();

            // Social Links
            $table->string('facebook')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('youtube')->nullable();
            $table->string('twitter')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('threads')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
