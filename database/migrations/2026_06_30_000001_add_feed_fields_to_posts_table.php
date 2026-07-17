<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('short_description')->nullable()->after('content');
            $table->text('image_description')->nullable()->after('short_description');
            $table->json('tags')->nullable()->after('image_description');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['short_description', 'image_description', 'tags']);
        });
    }
};
