<?php

namespace App\Models;

use App\Traits\Purifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasFactory, Purifiable;

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'read_at',
    ];

    /**
     * Fields to auto-purify when saving.
     *
     * HTML Purifier whitelist to prevent XSS in contact form submissions.
     */
    public function getPurifiableFields(): array
    {
        return ['name', 'subject', 'message'];
    }

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * Scope: unread messages only.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope: read messages only.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Mark the message as read.
     */
    public function markAsRead(): self
    {
        $this->update(['read_at' => now()]);

        return $this;
    }
}
