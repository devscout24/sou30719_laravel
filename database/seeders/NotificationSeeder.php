<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    public function run()
    {
        $endUsers = User::whereHas('roles', function($q) {
            $q->whereIn('name', ['user', 'demo']);
        })->get();

        $admins = User::whereHas('roles', function($q) {
            $q->where('name', 'admin');
        })->get();

        // Demo notifications for customers (generic)
        $customerNotifications = [
            [
                'title' => 'Welcome!',
                'message' => 'Thanks for joining — explore your account and settings.',
                'type' => 'welcome'
            ],
            [
                'title' => 'Account Update',
                'message' => 'Your profile was updated successfully.',
                'type' => 'account_update'
            ],
            [
                'title' => 'Special Offer',
                'message' => 'Get 20% off your next purchase.',
                'type' => 'promotion'
            ]
        ];

        // Demo notifications for admins
        $adminNotifications = [
            [
                'title' => 'System Report',
                'message' => 'Daily system health report is available.',
                'type' => 'system_report'
            ],
            [
                'title' => 'New User Registered',
                'message' => 'A new user has signed up and requires review.',
                'type' => 'user_registered'
            ]
        ];

        // Send notifications to user/demo roles
        foreach ($endUsers as $endUser) {
            foreach ($customerNotifications as $notification) {
                $endUser->notify(new GeneralNotification(
                    $notification['title'],
                    $notification['message'],
                    $notification['type'],
                    $notification['data'] ?? []
                ));
            }
        }


        // Send notifications to admins
        foreach ($admins as $admin) {
            foreach ($adminNotifications as $notification) {
                $admin->notify(new GeneralNotification(
                    $notification['title'],
                    $notification['message'],
                    $notification['type'],
                    $notification['data'] ?? []
                ));
            }
        }
    }
}