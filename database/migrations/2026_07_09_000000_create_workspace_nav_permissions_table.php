<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_nav_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('nav_key', 50);
            $table->timestamps();

            $table->unique(['workspace_id', 'nav_key']);
        });

        // Existing workspaces default to full access across all fixed nav sections.
        $navKeys = ['ai_pal', 'discovery', 'friends', 'chat'];
        $now = now();

        $rows = [];
        foreach (DB::table('workspaces')->pluck('id') as $workspaceId) {
            foreach ($navKeys as $navKey) {
                $rows[] = [
                    'workspace_id' => $workspaceId,
                    'nav_key' => $navKey,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($rows)) {
            DB::table('workspace_nav_permissions')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_nav_permissions');
    }
};
