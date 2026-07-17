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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('user_subscriptions')->nullOnDelete();

            $table->string('stripe_payment_intent_id')->nullable();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('usd');

            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');

            $table->string('invoice_url')->nullable();
            $table->string('receipt_url')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
