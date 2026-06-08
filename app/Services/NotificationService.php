<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public static function send(User $user, string $type, string $title, string $message)
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
    }

    public static function sendToMany(array $userIds, string $type, string $title, string $message)
    {
        $notifications = [];
        foreach ($userIds as $userId) {
            $notifications[] = [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Notification::insert($notifications);
    }

    public static function sendToAll(string $type, string $title, string $message)
    {
        $userIds = User::pluck('id')->toArray();
        self::sendToMany($userIds, $type, $title, $message);
    }

    public static function success(User $user, string $title, string $message)
    {
        return self::send($user, 'success', $title, $message);
    }

    public static function error(User $user, string $title, string $message)
    {
        return self::send($user, 'error', $title, $message);
    }

    public static function warning(User $user, string $title, string $message)
    {
        return self::send($user, 'warning', $title, $message);
    }

    public static function info(User $user, string $title, string $message)
    {
        return self::send($user, 'info', $title, $message);
    }
}
