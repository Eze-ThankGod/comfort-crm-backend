<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = auth()->user()
            ->notifications()
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->success($notifications);
    }

    public function unread(): JsonResponse
    {
        $notifications = auth()->user()
            ->unreadNotifications()
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'count'         => $notifications->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $notification = auth()->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json(['status' => 'success', 'message' => 'Marked as read']);
    }

    public function markAllAsRead(): JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['status' => 'success', 'message' => 'All notifications marked as read']);
    }

    public function destroy(string $id): JsonResponse
    {
        auth()->user()
            ->notifications()
            ->findOrFail($id)
            ->delete();

        return response()->json(['status' => 'success', 'message' => 'Notification deleted']);
    }
}
