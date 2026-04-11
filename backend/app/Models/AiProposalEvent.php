<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit record for every BookingActionProposal lifecycle event.
 *
 * Events: shown (proposal presented to user), confirmed, declined.
 *
 * @property int $id
 * @property int $user_id
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
        'proposal_hash',
        'action_type',
        'user_decision',
        'downstream_result',
    ];

    /**
     * @return BelongsTo<User, self>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
