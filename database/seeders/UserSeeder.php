<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guardName = config('auth.defaults.guard', 'web');

        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'password' => Hash::make('12345678'),
                'status' => 'active',
            ]
        );
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guardName]);
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
        Role::firstOrCreate(['name' => 'user', 'guard_name' => $guardName]);
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
        Role::firstOrCreate(['name' => 'demo', 'guard_name' => $guardName]);
        $demo->assignRole('demo');

        // firstOrCreate only applies its attributes on first creation — force
        // verification here too, so re-running this seeder against a DB where
        // these accounts already existed (unverified) still fixes them.
        foreach ([$admin, $user, $demo] as $seededUser) {
            if (!$seededUser->email_verified_at) {
                $seededUser->forceFill(['email_verified_at' => now()])->save();
            }
        }
    }
}
