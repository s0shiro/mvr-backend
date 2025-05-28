<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'type',
        'user_id',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at'
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime'
    ];

    // Relationship to the user who receives the notification
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Polymorphic relationship to the related model
    public function notifiable()
    {
        return $this->morphTo();
    }

    // Mark notification as read
    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }

    // Scope for unread notifications
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
