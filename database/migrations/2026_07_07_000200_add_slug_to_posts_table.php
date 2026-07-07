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
        Schema::table('posts', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('id');
        });

        foreach (DB::table('posts')->whereNull('slug')->get(['id', 'topic', 'title']) as $row) {
            $base = Str::slug($row->topic ?: $row->title ?: 'post') ?: 'post';

            DB::table('posts')->where('id', $row->id)->update([
                'slug' => $base . '-' . $row->id,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
