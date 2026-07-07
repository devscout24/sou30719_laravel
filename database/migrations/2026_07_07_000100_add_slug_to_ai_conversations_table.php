<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('id');
        });

        foreach (DB::table('ai_conversations')->whereNull('slug')->get(['id']) as $row) {
            DB::table('ai_conversations')->where('id', $row->id)->update([
                'slug' => 'conv-' . $row->id . '-' . Str::lower(Str::random(6)),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
