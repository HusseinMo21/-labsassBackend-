<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RefreshToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'device_id',
        'device_name',
        'ip_address',
        'user_agent',
        'expires_at',
        'last_used_at',
        'is_revoked',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_revoked' => 'boolean',
    ];

    /**
     * Get the user that owns the refresh token
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new refresh token
     */
    public static function generate(User $user, array $deviceInfo = []): self
    {
        // Revoke all existing tokens for this user and device
        if (isset($deviceInfo['device_id'])) {
            static::where('user_id', $user->id)
                  ->where('device_id', $deviceInfo['device_id'])
                  ->update(['is_revoked' => true]);
        }

        return static::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'device_id' => $deviceInfo['device_id'] ?? null,
            'device_name' => $deviceInfo['device_name'] ?? null,
            'ip_address' => $deviceInfo['ip_address'] ?? null,
            'user_agent' => $deviceInfo['user_agent'] ?? null,
            'expires_at' => Carbon::now()->addDays(30), // 30 days
        ]);
    }

    /**
     * Check if the token is valid
     */
    public function isValid(): bool
    {
        return !$this->is_revoked && $this->expires_at->isFuture();
    }

    /**
     * Mark token as used
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => Carbon::now()]);
    }

    /**
     * Revoke the token
     */
    public function revoke(): void
    {
        $this->update(['is_revoked' => true]);
    }

    /**
     * Revoke all tokens for a user
     */
    public static function revokeAllForUser(User $user): void
    {
        static::where('user_id', $user->id)->update(['is_revoked' => true]);
    }

    /**
     * Clean up expired tokens
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', Carbon::now())->delete();
    }
}
