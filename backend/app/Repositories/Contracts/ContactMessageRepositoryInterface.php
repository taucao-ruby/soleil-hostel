<?php

namespace App\Repositories\Contracts;

use App\Models\ContactMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * ContactMessageRepositoryInterface - Data access abstraction for ContactMessage domain
 *
 * DESIGN PRINCIPLES:
 * - Contains ONLY pure data access logic (no business rules, no validation)
 * - Returns Eloquent models/collections (no DTOs or transformations)
 * - Throws same exceptions as direct Eloquent calls (e.g., ModelNotFoundException)
 */
interface ContactMessageRepositoryInterface
{
    // ========== BASIC CRUD OPERATIONS ==========

    /**
     * Find a contact message by ID or throw exception.
     *
     * @param  int  $id  ContactMessage ID
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdOrFail(int $id): ContactMessage;

    /**
     * Create a new contact message.
     *
     * @param  array  $data  Validated contact message attributes
     */
    public function create(array $data): ContactMessage;

    // ========== LISTING QUERIES ==========

    /**
     * Get paginated contact messages, optionally filtered by read status.
     *
     * @param  int  $perPage  Items per page (capped at 100)
     * @param  string|null  $status  Filter: 'read', 'unread', or null for all
     */
    public function getPaginated(int $perPage = 15, ?string $status = null): LengthAwarePaginator;

    // ========== STATUS OPERATIONS ==========

    /**
     * Mark a contact message as read.
     */
    public function markAsRead(ContactMessage $message): ContactMessage;
}
