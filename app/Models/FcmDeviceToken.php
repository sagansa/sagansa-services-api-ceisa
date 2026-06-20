<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * FCM device tokens untuk push notification ke mobiles/ceisa.
 * user_id merujuk ke sagansa_user.users (cross-database, tanpa FK).
 */
class FcmDeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'device_token',
        'platform',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Register or refresh a device token.
     */
    public static function register(string $userId, string $token, string $platform = 'android'): self
    {
        return static::updateOrCreate(
            ['device_token' => $token],
            [
                'user_id' => $userId,
                'platform' => $platform,
                'is_active' => true,
                'last_used_at' => now(),
            ],
        );
    }

    /**
     * Active tokens for a given user.
     */
    public static function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId)->where('is_active', true);
    }
}