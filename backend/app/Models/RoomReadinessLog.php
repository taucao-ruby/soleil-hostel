<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoomReadinessStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit log for room readiness transitions.
 */
class RoomReadinessLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'room_id',
        'stay_id',
        'from_status',
        'to_status',
        'changed_at',
        'changed_by',
        'reason',
    ];

    protected $casts = [
        'from_status' => RoomReadinessStatus::class,
        'to_status' => RoomReadinessStatus::class,
        'changed_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(Stay::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
