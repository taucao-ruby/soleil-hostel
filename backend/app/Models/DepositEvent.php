<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepositStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit row for a deposit lifecycle transition.
 *
 * Writes are made through Deposit::transitionTo. There is no update path:
 * `$timestamps = false` and only `created_at` exists in the schema.
 *
 * @property int $id
 * @property int $booking_id
 * @property DepositStatus $from_status
 * @property DepositStatus $to_status
 * @property int $refund_percent
 * @property int|null $refund_amount
 * @property string|null $reason
 * @property int|null $actor_id
 * @property string|null $actor_email
 * @property string|null $actor_role
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 */
final class DepositEvent extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'booking_id',
        'from_status',
        'to_status',
        'refund_percent',
        'refund_amount',
        'reason',
        'actor_id',
        'actor_email',
        'actor_role',
        'metadata',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'booking_id' => 'integer',
        'from_status' => DepositStatus::class,
        'to_status' => DepositStatus::class,
        'refund_percent' => 'integer',
        'refund_amount' => 'integer',
        'actor_id' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Append-only invariant: forbid UPDATE writes to existing rows.
     *
     * INSERT (creating new rows) is allowed; saving a hydrated row that
     * already has an id and is dirty is rejected.
     */
    protected static function booted(): void
    {
        static::updating(function (self $event): bool {
            throw new \LogicException('deposit_events is append-only; rows cannot be updated.');
        });

        static::deleting(function (self $event): bool {
            throw new \LogicException('deposit_events is append-only; rows cannot be deleted.');
        });
    }
}
