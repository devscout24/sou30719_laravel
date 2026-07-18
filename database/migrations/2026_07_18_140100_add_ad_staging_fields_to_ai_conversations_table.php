<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Staging area for the Market Place workspace's structured ad form,
     * mirrored onto the `posts` table's ad fields once the draft is approved.
     */
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->enum('ad_type', ['product', 'service'])->nullable()->after('tags');
            $table->string('category')->nullable()->after('ad_type');
            $table->string('product_url')->nullable()->after('category');
            $table->decimal('discount_percentage', 5, 2)->nullable()->after('product_url');
            $table->boolean('show_sale_badge')->default(false)->after('discount_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn(['ad_type', 'category', 'product_url', 'discount_percentage', 'show_sale_badge']);
        });
    }
};
