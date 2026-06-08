<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    use ApiResponse;

    public function notification(Request $request)
    {
        $user = Auth::guard('api')->user();

        $notifications = $user->notifications()->orderBy('created_at', 'desc');

        if ($request->filled('date')) {
            $filterDate = Carbon::parse($request->date)->toDateString();
            $notifications->whereDate('created_at', $filterDate);
        }

        $notifications = $notifications->get();

        $formattedNotifications = $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
                'time_ago' => $notification->created_at->diffForHumans(),
            ];
        });

        return $this->success($formattedNotifications, 'Notifications fetched successfully', 200);
    }

    public function markAllRead()
    {
        $user = Auth::guard('api')->user();
        $user->unreadNotifications->markAsRead();

        return $this->success([], 'All notifications marked as read', 200);
    }

    public function markAllUnread()
    {
        $user = Auth::guard('api')->user();
        $user->notifications()->update(['read_at' => null]);

        return $this->success([], 'All notifications marked as unread', 200);
    }

    public function deleteAll()
    {
        $user = Auth::guard('api')->user();
        $user->notifications()->delete();

        return $this->success([], 'All notifications deleted', 200);
    }

    public function deleteNotification(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|string|exists:notifications,id'
        ]);

        $user = Auth::guard('api')->user();
        $notification = $user->notifications()->where('id', $request->notification_id)->first();

        if (!$notification) {
            return $this->error('Notification not found', 404);
        }

        $notification->delete();

        return $this->success([], 'Notification deleted successfully', 200);
    }

    public function markNotificationRead(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|string|exists:notifications,id'
        ]);

        $user = Auth::guard('api')->user();
        $notification = $user->notifications()->where('id', $request->notification_id)->first();

        if (!$notification) {
            return $this->error('Notification not found', 404);
        }

        $notification->markAsRead();

        return $this->success([], 'Notification marked as read', 200);
    }

    public function markNotificationUnread(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|string|exists:notifications,id'
        ]);

        $user = Auth::guard('api')->user();
        $notification = $user->notifications()->where('id', $request->notification_id)->first();

        if (!$notification) {
            return $this->error('Notification not found', 404);
        }

        $notification->update(['read_at' => null]);

        return $this->success([], 'Notification marked as unread', 200);
    }
}
