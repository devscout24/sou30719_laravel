<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\CompanySettingsSeeder;
use Database\Seeders\DynamicPageSeeder;
use Database\Seeders\NotificationSeeder;
use Database\Seeders\WorkspaceSeeder;
use Database\Seeders\PostSeeder;
use Database\Seeders\ChatSeeder;
use Database\Seeders\AiSuggestedPromptSeeder;
use Database\Seeders\SubscriptionPlanSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            NotificationSeeder::class,
            CompanySettingsSeeder::class,
            DynamicPageSeeder::class,
            WorkspaceSeeder::class,
            AiSuggestedPromptSeeder::class,
            SubscriptionPlanSeeder::class,
            PostSeeder::class,
            ChatSeeder::class,
        ]);


    }
}
