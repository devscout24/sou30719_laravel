<?php

namespace Database\Seeders;

use App\Models\MatchTopic;
use Illuminate\Database\Seeder;

class MatchTopicSeeder extends Seeder
{
    protected const FIXED_TOPICS = [
        ['slug' => 'newest', 'name' => 'Newest', 'sort_order' => 1],
        ['slug' => 'local', 'name' => 'Local', 'sort_order' => 2],
        ['slug' => 'friendship', 'name' => 'Friendship', 'sort_order' => 3],
        ['slug' => 'long_term', 'name' => 'Long Term', 'sort_order' => 4],
        ['slug' => 'marriage', 'name' => 'Marriage', 'sort_order' => 5],
        ['slug' => 'open_to_dating', 'name' => 'Open to Dating', 'sort_order' => 6],
    ];

    public function run(): void
    {
        foreach (self::FIXED_TOPICS as $topic) {
            MatchTopic::updateOrCreate(
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
