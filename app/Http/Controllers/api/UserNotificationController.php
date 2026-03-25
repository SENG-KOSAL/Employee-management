<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    public function unread(Request $request)
    {
        $user = $request->user();

        $perPage = (int) $request->query('per_page', 20);
        $notifications = $user->unreadNotifications()->paginate($perPage)->withQueryString();

        return response()->json($notifications);
    }

    public function markAsRead(Request $request, string $notificationId)
    {
        $user = $request->user();

        $notification = $user->notifications()->where('id', $notificationId)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All unread notifications marked as read']);
    }
}
