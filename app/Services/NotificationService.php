<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class NotificationService
{
    /**
     * Create a new notification
     */
    public function create(string $type, Model $notifiable, array $data, ?User $user = null)
    {
        try {
            if (!$user && !isset($data['user_id'])) {
                \Log::warning('Attempting to create notification without user', [
                    'type' => $type,
                    'notifiable_type' => get_class($notifiable),
                    'notifiable_id' => $notifiable->id
                ]);
                return null;
            }

            return Notification::create([
                'type' => $type,
                'user_id' => $user?->id,
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create notification: ' . $e->getMessage(), [
                'type' => $type,
                'user_id' => $user?->id,
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id,
                'data' => $data
            ]);
            return null;
        }
    }

    /**
     * Create notifications for all admins and managers
     */
    public function notifyAdmins(string $type, Model $notifiable, array $data)
    {
        $users = User::role(['admin', 'manager'])->get();
        foreach ($users as $user) {
            $this->create($type, $notifiable, $data, $user);
            // Send email notification if user has email
            if ($user->email) {
                $subject = $data['message'] ?? 'Notification';
                $message = $data['message'] ?? '';
                $details = $data;
                try {
                    \Mail::to($user->email)->send(new \App\Mail\UserNotificationMail($subject, $message, $details));
                } catch (\Exception $e) {
                    \Log::error('Failed to send admin/manager notification email: ' . $e->getMessage(), [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ]);
                }
            }
        }
    }

    /**
     * Create a notification for a specific user
     */
    public function notifyUser(int $userId, string $type, Model $notifiable, array $data)
    {
        $user = User::findOrFail($userId);
        $notification = $this->create($type, $notifiable, $data, $user);

        // Send email notification if user has email
        if ($user->email) {
            $subject = $data['message'] ?? 'Notification';
            $message = $data['message'] ?? '';
            $details = $data;
            try {
                \Mail::to($user->email)->send(new \App\Mail\UserNotificationMail($subject, $message, $details));
            } catch (\Exception $e) {
                \Log::error('Failed to send notification email: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }
        }

        return $notification;
    }

    /**
     * Get notifications for a user (cursor-based, returns only necessary fields)
     * @param User $user
     * @param bool $unreadOnly
     * @param int|null $cursor Notification ID to start after (for pagination)
     * @param int $limit Number of notifications to return
     * @return array
     */
    public function getUserNotifications(User $user, bool $unreadOnly = false, ?int $cursor = null, int $limit = 5)
    {
        $query = Notification::where('user_id', $user->id)
            ->with('notifiable')
            ->orderByDesc('id');

        if ($unreadOnly) {
            $query->unread();
        }
        if ($cursor) {
            $query->where('id', '<', $cursor);
        }

        $notifications = $query->limit($limit + 1)
            ->get(['id', 'type', 'data', 'read_at', 'created_at', 'notifiable_type', 'notifiable_id']);

        $hasMore = $notifications->count() > $limit;
        $items = $notifications->take($limit);

        $result = $items->map(function ($notification) {
            $notifiable = $notification->notifiable;
            $notifiableData = null;
            if ($notifiable && is_object($notifiable)) {
                if ($notification->notifiable_type === 'App\\Models\\Payment') {
                    $notifiableData = [
                        'id' => $notifiable->id,
                        'booking_id' => $notifiable->booking_id ?? null,
                        'status' => $notifiable->status ?? null,
                        'type' => $notifiable->type ?? null,
                        'method' => $notifiable->method ?? null,
                        'approved_at' => $notifiable->approved_at ?? null,
                    ];
                } elseif ($notification->notifiable_type === 'App\\Models\\Booking') {
                    $notifiableData = [
                        'id' => $notifiable->id,
                        'status' => $notifiable->status ?? null,
                        'type' => $notifiable->type ?? null,
                        'approved_at' => $notifiable->approved_at ?? null,
                    ];
                }
            }
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
                'notifiable_type' => $notification->notifiable_type,
                'notifiable_id' => $notification->notifiable_id,
                'notifiable' => $notifiableData,
            ];
        });

        $nextCursor = $hasMore ? $items->last()->id : null;

        return [
            'notifications' => $result->values(),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore
        ];
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification)
    {
        $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(User $user)
    {
        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Get count of unread notifications for a user
     */
    public function getUnreadCount(User $user)
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }
}
