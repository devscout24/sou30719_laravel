<?php

namespace Database\Seeders;

use App\Models\Workspace;
use Illuminate\Database\Seeder;

class WorkspaceSeeder extends Seeder
{
    /**
     * The canonical set of AI Pal workspaces. Anything seeded previously under
     * a slug not listed here is removed — this seeder is the source of truth
     * for which workspaces exist.
     */
    public function run(): void
    {
        $workspaces = [
            [
                'title'        => 'Social Post',
                'description'  => 'Share updates, connect with people, and stay part of what\'s happening.',
                'prompt'       => 'I want to create a Post',
                'slug'         => Workspace::SLUG_SOCIAL_POST,
                'is_supported' => true,
                'status'       => 'active',
                'sort_order'   => 1,
                'nav_keys'     => ['ai_pal', 'discovery'],
            ],
            [
                'title'        => 'Matches',
                'description'  => 'Tell AI Pal what you\'re looking for and get matched with people on your dating profile.',
                'prompt'       => 'I want to find matches',
                'slug'         => Workspace::SLUG_MATCHES,
                'is_supported' => true,
                'status'       => 'active',
                'sort_order'   => 2,
                'nav_keys'     => ['ai_pal', 'discovery'],
            ],
            [
                'title'        => 'Market Place',
                'description'  => 'Create a product or service advertisement post for the marketplace.',
                'prompt'       => 'I want to create an advertisement',
                'slug'         => Workspace::SLUG_MARKET_PLACE,
                'is_supported' => true,
                'status'       => 'active',
                'sort_order'   => 3,
                'nav_keys'     => ['ai_pal', 'discovery'],
            ],
            [
                'title'        => 'Event',
                'description'  => 'Create and share an event with your community.',
                'prompt'       => 'I want to create an event',
                'slug'         => Workspace::SLUG_EVENT,
                'is_supported' => false,
                'status'       => 'active',
                'sort_order'   => 4,
                'nav_keys'     => ['ai_pal'],
            ],
            [
                'title'        => 'Interest Hub',
                'description'  => 'Connect with people who share your interests.',
                'prompt'       => 'I want to explore interest hub',
                'slug'         => Workspace::SLUG_INTEREST_HUB,
                'is_supported' => false,
                'status'       => 'active',
                'sort_order'   => 5,
                'nav_keys'     => ['ai_pal'],
            ],
            [
                'title'        => 'Personal Courier',
                'description'  => 'Request a personal courier or delivery.',
                'prompt'       => 'I want to request a personal courier',
                'slug'         => Workspace::SLUG_PERSONAL_COURIER,
                'is_supported' => false,
                'status'       => 'active',
                'sort_order'   => 6,
                'nav_keys'     => ['ai_pal'],
            ],
        ];

        $seededSlugs = [];

        foreach ($workspaces as $data) {
            $navKeys = $data['nav_keys'];
            unset($data['nav_keys']);

            $workspace = Workspace::updateOrCreate(['slug' => $data['slug']], $data);

            $workspace->navPermissions()->whereNotIn('nav_key', $navKeys)->delete();

            foreach ($navKeys as $key) {
                $workspace->navPermissions()->firstOrCreate(['nav_key' => $key]);
            }

            $seededSlugs[] = $data['slug'];
        }

        // Retire any workspace no longer part of the canonical set above.
        Workspace::whereNotIn('slug', $seededSlugs)->get()->each->delete();
    }
}
