<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanySettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CompanySetting::updateOrCreate(

            // Always use ID = 1 (Single Record System)
            ['id' => 1],

            [

                // Company Info
                'company_name'     => 'Your Company Name',
                'website'          => 'https://yourcompany.com',
                'email'            => 'info@yourcompany.com',
                'hotline'          => '+880123456789',
                'address'          => 'Dhaka, Bangladesh',
                'description'      => 'This is the official company description.',

                'logo'             => 'logo.png',

                // App Links
                'play_store_link'  => 'https://play.google.com/store/apps/details?id=yourapp',
                'apple_store_link' => 'https://apps.apple.com/app/id000000000',

                // Social Links
                'facebook'         => 'https://facebook.com/yourpage',
                'linkedin'         => 'https://linkedin.com/company/yourcompany',
                'youtube'          => 'https://youtube.com/@yourchannel',
                'twitter'          => 'https://x.com/yourhandle',
                'tiktok'           => 'https://tiktok.com/@yourhandle',
                'threads'          => 'https://threads.net/@yourhandle',
            ]
        );
    }
}
