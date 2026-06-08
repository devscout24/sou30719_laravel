<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'password' => Hash::make('12345678'),
                'status' => 'active',
            ]
        );
        $admin->assignRole('admin');

        $user = User::firstOrCreate(
            ['email' => 'user@user.com'],
            [
                'name' => 'User Account',
                'username' => 'user',
                'password' => Hash::make('12345678'),
                'status' => 'active',
            ]
        );
        $user->assignRole('user');

        $demo = User::firstOrCreate(
            ['email' => 'demo@demo.com'],
            [
                'name' => 'Demo User',
                'username' => 'demo',
                'password' => Hash::make('12345678'),
                'status' => 'active',
            ]
        );
        $demo->assignRole('demo');
    }
}
