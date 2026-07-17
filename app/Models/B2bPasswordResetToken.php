<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class B2bPasswordResetToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }

    public static function createForUser(User $user, int $hoursValid = 24): string
    {
        static::query()->where('user_id', $user->id)->delete();

        $plainToken = Str::random(64);

        static::query()->create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addHours($hoursValid),
        ]);

        return $plainToken;
    }

    public static function findValid(string $plainToken): ?self
    {
        return static::query()
            ->where('token', hash('sha256', $plainToken))
            ->where('expires_at', '>', now())
            ->first();
    }
}
