<?php

namespace Database\Seeders;

use App\Models\DynamicPage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DynamicPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ADD Multiple Dynamic Pages
        $dynamicPages = [
            [
                'page_name' => 'About Us',
                'content' => 'This is the About Us page content.',
                'is_active' => true,
            ],
            [
                'page_name' => 'Privacy Policy',
                'content' => 'This is the Privacy Policy page content.',
                'is_active' => true,
            ],
            [
                'page_name' => 'Terms of Service',
                'content' => 'This is the Terms of Service page content.',
                'is_active' => true,
            ],
        ];

        foreach ($dynamicPages as $page) {
            DynamicPage::create($page);
        }
    }
}
