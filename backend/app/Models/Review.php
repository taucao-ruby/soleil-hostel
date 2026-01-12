<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Purifiable;

/**
 * Review Model
 * 
 * Auto-purify HTML content từ guest reviews
 * Title + content được sanitize tự động qua trait Purifiable
 */
class Review extends Model
{
    use HasFactory, Purifiable;

    protected $fillable = [
        'title',
        'content',
        'rating',
        'room_id',
        'user_id',
        'guest_name',
        'guest_email',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Fields to auto-purify when saving
     * 
     * Dùng HTML Purifier whitelist, chứ không phải regex blacklist
     * Title + content được strip safe tags, block <script>, on*, javascript:, v.v.
     * 
     * @return array<string>
     */
    public function getPurifiableFields(): array
    {
        return [
            'title',      // Review title
            'content',    // Review body - allow basic HTML (b, i, p, br, links)
            'guest_name', // Guest who wrote review
        ];
    }

    /**
     * Relationships
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the booking this review belongs to.
     * 
     * Required for ReviewPolicy ownership checks via $review->booking->user_id.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Scopes
     */
    public function scopeApproved($query)
    {
        return $query->where('approved', true);
    }

    public function scopeHighRated($query)
    {
        return $query->where('rating', '>=', 4);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }
}
