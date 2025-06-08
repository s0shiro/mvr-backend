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
     * Get notifications for a user
     */
    public function getUserNotifications(User $user, bool $unreadOnly = false)
    {
        $query = Notification::where('user_id', $user->id)
            ->with('notifiable')
            ->orderByDesc('created_at');

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->get();
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
