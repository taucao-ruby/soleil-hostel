<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Durable record of an AI-generated BookingActionProposal.
 *
 * The Cache envelope is the fast-path lookup the confirmation controller
 * uses for proposer binding; this table is the contract used at confirm
 * time to revalidate the proposal against current room/price/policy state
 * (AI-006) and to gate confirm on a prior shown event (AI-005).
 *
 * @property int $id
 * @property string $proposal_hash
 * @property int|null $user_id
 * @property string $action_type
 * @property int|null $room_id
 * @property Carbon|null $check_in
 * @property Carbon|null $check_out
 * @property int|null $quoted_price_cents
 * @property string $context_version
 * @property array<string, mixed> $proposed_params
 * @property array<string, mixed> $risk_assessment
 * @property Carbon $expires_at
 * @property Carbon|null $shown_at
 * @property string|null $decision
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AiProposal extends Model
{
    protected $fillable = [
        'proposal_hash',
        'user_id',
        'action_type',
        'room_id',
        'check_in',
        'check_out',
        'quoted_price_cents',
        'context_version',
        'proposed_params',
        'risk_assessment',
        'expires_at',
        'shown_at',
        'decision',
        'decided_at',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'quoted_price_cents' => 'integer',
        'proposed_params' => 'array',
        'risk_assessment' => 'array',
        'expires_at' => 'datetime',
        'shown_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     *
     * @phpstan-return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     *
     * @phpstan-return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isShown(): bool
    {
        return $this->shown_at !== null;
    }

    public function isDecided(): bool
    {
        return $this->decision !== null;
    }
}
