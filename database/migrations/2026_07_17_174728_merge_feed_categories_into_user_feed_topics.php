<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canonical fixed topics, used as a fallback when 'feed_categories' has no rows
     * (fresh installs that never ran FeedCategorySeeder).
     */
    protected array $defaults = [
        ['name' => 'Newest', 'slug' => 'newest', 'icon' => 'clock', 'tag_keywords' => null, 'sort_order' => 1],
        ['name' => 'Local', 'slug' => 'local', 'icon' => 'map-pin', 'tag_keywords' => null, 'sort_order' => 2],
        ['name' => 'Friendship', 'slug' => 'friendship', 'icon' => 'users', 'tag_keywords' => null, 'sort_order' => 3],
        ['name' => 'Trending', 'slug' => 'trending', 'icon' => 'trending-up', 'tag_keywords' => null, 'sort_order' => 4],
        [
            'name' => 'Olympics',
            'slug' => 'olympics',
            'icon' => 'award',
            'tag_keywords' => ['olympics', 'olympic', 'athlete', 'medal', 'sports', 'games', 'championship', 'tournament'],
            'sort_order' => 5,
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_feed_topics', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('slug')->nullable()->unique()->after('user_id');
            $table->string('icon')->nullable()->after('name');
            $table->json('tag_keywords')->nullable()->after('icon');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('tag_keywords');
            $table->boolean('is_fixed')->default(false)->after('sort_order');
            $table->boolean('is_active')->default(true)->after('is_fixed');
        });

        $existing = Schema::hasTable('feed_categories')
            ? DB::table('feed_categories')->orderBy('sort_order')->get()
            : collect();

        $rows = $existing->isNotEmpty() ? $existing : collect($this->defaults);

        foreach ($rows as $row) {
            $row = (array) $row;
            $keywords = $row['tag_keywords'] ?? null;

            DB::table('user_feed_topics')->updateOrInsert(
                ['slug' => $row['slug']],
                [
                    'user_id'      => null,
                    'name'         => $row['name'],
                    'icon'         => $row['icon'] ?? null,
                    'tag_keywords' => is_string($keywords) || $keywords === null ? $keywords : json_encode($keywords),
                    'sort_order'   => $row['sort_order'] ?? 0,
                    'is_fixed'     => true,
                    'is_active'    => $row['is_active'] ?? true,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]
            );
        }

        Schema::dropIfExists('feed_categories');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('feed_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->json('tag_keywords')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('user_feed_topics')->where('is_fixed', true)->orderBy('sort_order')->get()->each(function ($row) {
            DB::table('feed_categories')->insert([
                'name'         => $row->name,
                'slug'         => $row->slug,
                'icon'         => $row->icon,
                'tag_keywords' => $row->tag_keywords,
                'sort_order'   => $row->sort_order,
                'is_active'    => $row->is_active,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        });

        DB::table('user_feed_topics')->where('is_fixed', true)->delete();

        Schema::table('user_feed_topics', function (Blueprint $table) {
            $table->dropColumn(['slug', 'icon', 'tag_keywords', 'sort_order', 'is_fixed', 'is_active']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
