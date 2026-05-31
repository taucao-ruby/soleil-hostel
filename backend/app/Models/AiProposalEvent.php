<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit record for every BookingActionProposal lifecycle event.
 *
 * Events: shown (proposal presented to user), confirmed, declined, errored.
 *
 * @property int $id
 * @property int|null $user_id // nullable since Batch 4 (3F): user deletion sets this null, audit row survives
 * @property string|null $actor_email // denormalised at write time so audit survives user deletion
 * @property string|null $actor_role
 * @property string|null $actor_display_name
 * @property string $proposal_hash
 * @property string $action_type
 * @property string $user_decision
 * @property string|null $downstream_result
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class AiProposalEvent extends Model
{
    protected $fillable = [
        'user_id',
        'actor_email',
        'actor_role',
        'actor_display_name',
        'proposal_hash',
        'action_type',
        'user_decision',
        'downstream_result',
    ];

    /**
     * @return BelongsTo<User, $this>
     *
     * @phpstan-return BelongsTo<User, $this>
     *
     * @psalm-return BelongsTo<User, static>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
