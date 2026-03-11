<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Http\Request;

/**
 * AdminAuditService — Logs sensitive admin operations to admin_audit_logs.
 *
 * Usage:
 *   $this->auditService->log('booking.force_delete', 'booking', $id, ['reason' => $reason]);
 *
 * This service is intentionally simple — a thin wrapper around the model insert.
 * It captures the actor from the current auth context and IP from the request.
 */
class AdminAuditService
{
    public function __construct(
        private Request $request
    ) {}

    /**
     * Log an admin action.
     *
     * @param  string  $action  Action identifier (e.g. 'booking.force_delete', 'room.create')
     * @param  string  $resourceType  Resource type (e.g. 'booking', 'room', 'review')
     * @param  int|null  $resourceId  ID of the affected resource
     * @param  array<string, mixed>  $metadata  Additional context (reason, before/after values, etc.)
     */
    public function log(string $action, string $resourceType, ?int $resourceId = null, array $metadata = []): AdminAuditLog
    {
        return AdminAuditLog::create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata' => ! empty($metadata) ? $metadata : null,
            'ip_address' => $this->request->ip(),
            'created_at' => now(),
        ]);
    }
}
