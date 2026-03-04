<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Services\ContactMessageService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ContactMessageService $contactMessageService
    ) {}

    /**
     * Store a contact message in storage.
     *
     * INPUT SANITIZATION:
     * - email validation ensures valid email format
     * - message is purified using HTML Purifier whitelist
     * - Regex blacklist = 99% bypass. HTML Purifier = 0% bypass.
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        $contactMessage = $this->contactMessageService->store($request->validated());

        return $this->success($contactMessage, __('messages.contact_received'), 201);
    }

    /**
     * List all contact messages (admin only).
     * Paginated, sorted by newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');

        $messages = $this->contactMessageService->getPaginated($perPage, $status);

        return $this->success($messages, __('messages.contacts_retrieved'));
    }

    /**
     * Mark a contact message as read (admin only).
     */
    public function markAsRead(int $id): JsonResponse
    {
        $message = $this->contactMessageService->markAsRead($id);

        return $this->success($message, __('messages.contact_marked_read'));
    }
}
