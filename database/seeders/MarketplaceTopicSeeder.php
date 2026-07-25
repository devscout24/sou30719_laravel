<?php

namespace Database\Seeders;

use App\Models\MarketplaceTopic;
use Illuminate\Database\Seeder;

class MarketplaceTopicSeeder extends Seeder
{
    protected const FIXED_TOPICS = [
        ['slug' => 'products', 'name' => 'Products', 'sort_order' => 1],
        ['slug' => 'services', 'name' => 'Services', 'sort_order' => 2],
        ['slug' => 'cosmetics', 'name' => 'Cosmetics', 'sort_order' => 3],
        ['slug' => 'electronics', 'name' => 'Electronics', 'sort_order' => 4],
        ['slug' => 'food', 'name' => 'Food', 'sort_order' => 5],
    ];

    public function run(): void
    {
        foreach (self::FIXED_TOPICS as $topic) {
            MarketplaceTopic::updateOrCreate(
                ['slug' => $topic['slug'], 'user_id' => null],
                [
                    'name'       => $topic['name'],
                    'sort_order' => $topic['sort_order'],
                    'is_fixed'   => true,
                    'is_active'  => true,
                ]
            );
        }
    }
}
