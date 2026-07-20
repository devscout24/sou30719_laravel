<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dating_preferences', function (Blueprint $table) {
            $table->boolean('is_open_to_dating')->default(true)->after('relationship_goal');
        });
    }

    public function down(): void
    {
        Schema::table('dating_preferences', function (Blueprint $table) {
            $table->dropColumn('is_open_to_dating');
        });
    }
};
