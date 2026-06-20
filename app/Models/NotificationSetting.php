<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = [
        'channel',
        'is_enabled',
        'notify_normal',
        'notify_urgent',
        'target_recipient',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'notify_normal' => 'boolean',
        'notify_urgent' => 'boolean',
        'target_recipient' => 'array',
    ];

    public static function forChannel(string $channel): ?self
    {
        return static::where('channel', $channel)->first();
    }
}