<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Optional CSV attachment for the Market Place workspace's ad form —
     * stored and attached as-is, never parsed.
     */
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->string('csv_path')->nullable()->after('show_sale_badge');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('csv_path')->nullable()->after('show_sale_badge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('csv_path');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('csv_path');
        });
    }
};
