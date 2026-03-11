<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AdminAuditLog — Immutable record of sensitive admin operations.
 *
 * This model is append-only. No update or delete operations should be performed.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string $resource_type
 * @property int|null $resource_id
 * @property array|null $metadata
 * @property string|null $ip_address
 * @property \Carbon\Carbon $created_at
 */
class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'action',
        'resource_type',
        'resource_id',
        'metadata',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
