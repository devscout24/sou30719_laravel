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
            PostSeeder::class,
            ChatSeeder::class,
        ]);


    }
}
