<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get notifications for the authenticated user
     */
    public function index(Request $request)
    {
        $unreadOnly = $request->boolean('unread', false);
        $cursor = $request->input('cursor');
        $limit = $request->input('limit', 5);
        $notifications = $this->notificationService->getUserNotifications(
            Auth::user(),
            $unreadOnly,
            $cursor,
            $limit
        );

        return response()->json($notifications);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead($id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $this->notificationService->markAsRead($notification);

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $this->notificationService->markAllAsRead(Auth::user());
        return response()->json(['message' => 'All notifications marked as read']);
    }

    /**
     * Get unread notification count for the authenticated user
     */
    public function unreadCount()
    {
        $count = $this->notificationService->getUnreadCount(Auth::user());
        return response()->json(['unread_count' => $count]);
    }
}
