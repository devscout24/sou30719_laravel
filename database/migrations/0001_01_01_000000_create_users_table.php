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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->nullable()->unique();
            $table->string('avatar')->default('user.png');
            $table->string('name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address', 255)->nullable();

            // Email
            $table->string('email', 100)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_change_token')->nullable();
            $table->timestamp('email_change_token_expires_at')->nullable();
            $table->string('pending_email')->nullable();

            // Location — decimal for geo queries
            $table->string('location', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Auth
            $table->string('password')->nullable(); // nullable for Google users
            $table->enum('login_provider', ['email', 'google'])->default('email');
            $table->string('google_id')->nullable();
            $table->string('password_reset_token')->nullable();
            $table->timestamp('password_reset_token_expires_at')->nullable();

            // OTP
            $table->string('otp', 10)->nullable();
            $table->timestamp('otp_expired_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();

            // Profile
            $table->boolean('profile_completed')->default(false);

            // Status
            $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->string('fcm_token', 255)->nullable();

            // Soft delete
            $table->softDeletes();
            $table->string('delete_reason', 255)->nullable();

            $table->timestamps();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();

            $table->string('full_name', 100)->nullable();
            $table->text('bio')->nullable();

            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('dob')->nullable();

            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();

            $table->timestamps();
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();

            $table->string('language', 10)->default('en');
            $table->boolean('push_notifications')->default(true);

            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
