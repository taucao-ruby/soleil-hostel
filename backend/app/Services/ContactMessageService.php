<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ContactMessageService - Business logic for contact message operations
 *
 * Orchestrates repository calls with logging and audit trail.
 * No direct Eloquent calls — delegates data access to ContactMessageRepository.
 */
class ContactMessageService
{
    public function __construct(
        private readonly ContactMessageRepositoryInterface $contactMessageRepository
    ) {}

    /**
     * Store a new contact message with audit logging.
     *
     * @param  array  $validated  Validated and sanitized contact data
     */
    public function store(array $validated): ContactMessage
    {
        $contactMessage = $this->contactMessageRepository->create($validated);

        Log::info('Contact message received', [
            'id' => $contactMessage->id,
            'name' => $validated['name'],
            'email' => Str::mask($validated['email'], '*', 3),
            'subject' => $validated['subject'] ?? '',
        ]);

        return $contactMessage;
    }

    /**
     * Get paginated contact messages for admin listing.
     *
     * @param  int  $perPage  Items per page
     * @param  string|null  $status  Filter: 'read', 'unread', or null
     */
    public function getPaginated(int $perPage = 15, ?string $status = null): LengthAwarePaginator
    {
        return $this->contactMessageRepository->getPaginated($perPage, $status);
    }

    /**
     * Mark a contact message as read.
     */
    public function markAsRead(int $id): ContactMessage
    {
        $message = $this->contactMessageRepository->findByIdOrFail($id);

        return $this->contactMessageRepository->markAsRead($message);
    }
}
