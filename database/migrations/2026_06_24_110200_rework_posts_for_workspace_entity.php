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
        Schema::table('posts', function (Blueprint $table) {
            // The 'workspace' column carries a plain index — must be dropped explicitly
            // before the column itself, or SQLite refuses the drop (MySQL drops it implicitly).
            $table->dropIndex(['workspace']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['workspace', 'prompt']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('topic')->nullable()->after('workspace_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
            $table->dropColumn('topic');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('workspace')->default('social')->index();
            $table->text('prompt')->nullable();
        });
    }
};
