<?php

namespace Database\Seeders;

use App\Models\FeedCategory;
use Illuminate\Database\Seeder;

class FeedCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'         => 'Newest',
                'slug'         => 'newest',
                'icon'         => 'clock',
                'tag_keywords' => null,
                'sort_order'   => 1,
                'is_active'    => true,
            ],
            [
                'name'         => 'Local',
                'slug'         => 'local',
                'icon'         => 'map-pin',
                'tag_keywords' => null,
                'sort_order'   => 2,
                'is_active'    => true,
            ],
            [
                'name'         => 'Friendship',
                'slug'         => 'friendship',
                'icon'         => 'users',
                'tag_keywords' => null,
                'sort_order'   => 3,
                'is_active'    => true,
            ],
            [
                'name'         => 'Trending',
                'slug'         => 'trending',
                'icon'         => 'trending-up',
                'tag_keywords' => null,
                'sort_order'   => 4,
                'is_active'    => true,
            ],
            [
                'name'         => 'Olympics',
                'slug'         => 'olympics',
                'icon'         => 'award',
                'tag_keywords' => ['olympics', 'olympic', 'athlete', 'medal', 'sports', 'games', 'championship', 'tournament'],
                'sort_order'   => 5,
                'is_active'    => true,
            ],
        ];

        foreach ($categories as $category) {
            FeedCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
