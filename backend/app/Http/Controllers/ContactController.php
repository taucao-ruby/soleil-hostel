<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\ContactMessage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    use ApiResponse;

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
        $validated = $request->validated();

        $contactMessage = ContactMessage::create($validated);

        // Log as additional audit trail with masked email
        \Log::info('Contact message received', [
            'id' => $contactMessage->id,
            'name' => $validated['name'],
            'email' => \Illuminate\Support\Str::mask($validated['email'], '*', 3),
            'subject' => $validated['subject'] ?? '',
        ]);

        return $this->success($contactMessage, __('messages.contact_received'), 201);
    }

    /**
     * List all contact messages (admin only).
     * Paginated, sorted by newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status'); // 'read', 'unread', or null for all

        $query = ContactMessage::query()->orderBy('created_at', 'desc');

        if ($status === 'unread') {
            $query->unread();
        } elseif ($status === 'read') {
            $query->read();
        }

        $messages = $query->paginate(min($perPage, 100));

        return $this->success($messages, __('messages.contacts_retrieved'));
    }

    /**
     * Mark a contact message as read (admin only).
     */
    public function markAsRead(int $id): JsonResponse
    {
        $message = ContactMessage::findOrFail($id);
        $message->markAsRead();

        return $this->success($message, __('messages.contact_marked_read'));
    }
}
