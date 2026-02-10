<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\HtmlPurifierService;

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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
        ], [
            'name.required' => 'Name is required.',
            'name.max' => 'Name cannot exceed 255 characters.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email.',
            'subject.max' => 'Subject cannot exceed 255 characters.',
            'message.required' => 'Message cannot be empty.',
            'message.max' => 'Message cannot exceed 5000 characters.',
        ]);

        // Purify name, subject, and message using HTML Purifier (whitelist approach, not regex blacklist)
        // SEC-NEW-06: Purify all text inputs to prevent XSS
        $validated['name'] = HtmlPurifierService::purify($validated['name']);
        $validated['subject'] = HtmlPurifierService::purify($validated['subject'] ?? '');
        $validated['message'] = HtmlPurifierService::purify($validated['message']);

        // Persist the contact message to database
        // Note: ContactMessage model also has Purifiable trait as additional layer
        $contactMessage = ContactMessage::create($validated);

        // Log as additional audit trail with masked email
        \Log::info('Contact message received', [
            'id' => $contactMessage->id,
            'name' => $validated['name'],
            'email' => \Illuminate\Support\Str::mask($validated['email'], '*', 3),
            'subject' => $validated['subject'] ?? '',
        ]);

        return $this->success($contactMessage, 'Message received. We will get back to you soon.', 201);
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

        return $this->success($messages, 'Contact messages retrieved.');
    }

    /**
     * Mark a contact message as read (admin only).
     */
    public function markAsRead(int $id): JsonResponse
    {
        $message = ContactMessage::findOrFail($id);
        $message->markAsRead();

        return $this->success($message, 'Message marked as read.');
    }
}
