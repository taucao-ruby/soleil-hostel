<?php

namespace App\Repositories;

use App\Models\ContactMessage;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * EloquentContactMessageRepository - Eloquent implementation for ContactMessage data access
 *
 * Pure data access — no business logic, no validation, no HTTP concerns.
 */
class EloquentContactMessageRepository implements ContactMessageRepositoryInterface
{
    // ========== BASIC CRUD OPERATIONS ==========

    /**
     * {@inheritDoc}
     */
    public function findByIdOrFail(int $id): ContactMessage
    {
        return ContactMessage::findOrFail($id);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): ContactMessage
    {
        return ContactMessage::create($data);
    }

    // ========== LISTING QUERIES ==========

    /**
     * {@inheritDoc}
     */
    public function getPaginated(int $perPage = 15, ?string $status = null): LengthAwarePaginator
    {
        $query = ContactMessage::query()->orderBy('created_at', 'desc');

        if ($status === 'unread') {
            $query->unread();
        } elseif ($status === 'read') {
            $query->read();
        }

        return $query->paginate(min($perPage, 100));
    }

    // ========== STATUS OPERATIONS ==========

    /**
     * {@inheritDoc}
     */
    public function markAsRead(ContactMessage $message): ContactMessage
    {
        $message->markAsRead();

        return $message;
    }
}
