<?php

namespace Database\Seeders;

use App\Models\Workspace;
use Illuminate\Database\Seeder;

class WorkspaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workspaces = [
            [
                'title' => 'Curated Social Media',
                'description' => 'Share updates, connect with people, and stay part of what\'s happening.',
                'prompt' => 'I want to create a Post',
                'slug' => 'social_post',
                'is_supported' => true,
                'status' => 'active',
                'sort_order' => 1,
            ],
            [
                'title' => 'Advertisement Banner',
                'description' => 'Design and publish an advertisement banner for your business.',
                'prompt' => 'I want to create an Advertisement banner',
                'slug' => 'ad_banner',
                'is_supported' => false,
                'status' => 'active',
                'sort_order' => 2,
            ],
            [
                'title' => 'Dating Profile',
                'description' => 'Create a profile to meet new people.',
                'prompt' => 'I want to create a Profile for dating',
                'slug' => 'dating_profile',
                'is_supported' => false,
                'status' => 'active',
                'sort_order' => 3,
            ],
            [
                'title' => 'Curated Feed',
                'description' => 'Curate a feed of content around a specific topic.',
                'prompt' => 'I want to curate a feed on specific topic',
                'slug' => 'curated_feed',
                'is_supported' => false,
                'status' => 'active',
                'sort_order' => 4,
            ],
        ];

        foreach ($workspaces as $workspace) {
            Workspace::updateOrCreate(['slug' => $workspace['slug']], $workspace);
        }
    }
}
