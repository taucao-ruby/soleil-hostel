<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerificationCode extends Model
{
    protected $fillable = [
        'user_id',
        'code_hash',
        'expires_at',
        'attempts',
        'max_attempts',
        'last_sent_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: active codes (not consumed and not expired).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Whether the code has exhausted all allowed attempts.
     */
    public function isExhausted(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    /**
     * Whether the code has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
