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
            // Primary key and unique username
            $table->id();
            $table->string('username', 50)->nullable()->unique();
            // Default avatar set to 'user.png' for all users
            $table->string('avatar')->default('user.png');
            $table->string('name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address', 255)->nullable();
            // Email fields for verification and change requests
            $table->string('email', 100)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_change_token')->nullable();
            $table->timestamp('email_change_token_expires_at')->nullable();
            $table->string('pending_email')->nullable();
            // Location fields for geocoding
            $table->string('location', 255)->nullable();
            $table->string('longitude', 255)->nullable();
            $table->string('latitude', 255)->nullable();
            // Authentication fields
            $table->string('password');
            $table->string('password_reset_token')->nullable();
            $table->timestamp('password_reset_token_expires_at')->nullable();
            // OTP fields for two-factor authentication
            $table->string('otp', 10)->nullable();
            $table->timestamp('otp_expired_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            // Status fields for account management
            $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
            // Last login timestamp for user activity tracking
            $table->timestamp('last_login_at')->nullable();
            // FCM token for push notifications
            $table->string('fcm_token', 255)->nullable();
            // Soft deletes for user accounts
            $table->softDeletes();
            $table->string('delete_reason', 255)->nullable();

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
