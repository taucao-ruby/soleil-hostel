<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Purifiable;

class Booking extends Model
{
    use HasFactory, Purifiable, SoftDeletes;

    protected $fillable = [
        'room_id',
        'check_in',
        'check_out',
        'guest_name',
        'guest_email',
        'status',
        'user_id',
        'deleted_by',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Auto-purify these fields when saving
     * Dùng HTML Purifier whitelist, chứ không phải regex blacklist
     * (Regex XSS = 99% bypass. HTML Purifier = 0% bypass)
     */
    public function getPurifiableFields()
    {
        return ['guest_name'];
    }

    // ===== CONSTANTS =====
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = ['pending', 'confirmed'];

    /**
     * Get the room that owns the booking.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the user that made the booking.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ===== SCOPES =====

    /**
     * Scope: Load common relationships with column selection to prevent N+1
     * 
     * This is the PRIMARY scope to use in controllers. Loads room + user with only needed columns.
     * 
     * Usage: Booking::withCommonRelations()->get()
     */
    public function scopeWithCommonRelations(Builder $query): Builder
    {
        return $query
            ->with([
                'room' => fn($q) => $q->selectColumns(),
                'user' => fn($q) => $q->selectColumns(),
            ]);
    }

    /**
     * Scope: Select only commonly needed columns to reduce memory + bandwidth
     * 
     * Usage: Booking::selectColumns()->get()
     */
    public function scopeSelectColumns(Builder $query): Builder
    {
        return $query->select([
            'bookings.id',
            'bookings.room_id',
            'bookings.user_id',
            'bookings.check_in',
            'bookings.check_out',
            'bookings.guest_name',
            'bookings.guest_email',
            'bookings.status',
            'bookings.created_at',
            'bookings.updated_at',
        ]);
    }

    /**
     * Scope: Tìm các booking của phòng với ngày trùng lặp
     * 
     * Dùng half-open interval [check_in, check_out):
     * - Cho phép book_old.check_out == book_new.check_in (checkout sáng, check-in trưa cùng ngày)
     * 
     * @param Builder $query
     * @param int $roomId ID phòng
     * @param Carbon|\DateTime $checkIn Ngày check-in mới
     * @param Carbon|\DateTime $checkOut Ngày check-out mới
     * @return Builder
     */
    public function scopeOverlappingBookings(
        Builder $query,
        int $roomId,
        $checkIn,
        $checkOut,
        ?int $excludeBookingId = null
    ): Builder {
        // Đảm bảo các tham số là Carbon instance
        $checkIn = $checkIn instanceof Carbon ? $checkIn : Carbon::parse($checkIn);
        $checkOut = $checkOut instanceof Carbon ? $checkOut : Carbon::parse($checkOut);

        // Logic overlap với half-open interval [a1, b1) và [a2, b2):
        // Overlap xảy ra khi: a1 < b2 AND a2 < b1
        // 
        // Trong SQL: check_in < check_out_new AND check_out > check_in_new
        return $query
            ->where('room_id', $roomId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('check_in', '<', $checkOut) // Ngày bắt đầu của booking hiện tại < ngày kết thúc mới
            ->where('check_out', '>', $checkIn) // Ngày kết thúc của booking hiện tại > ngày bắt đầu mới
            ->when($excludeBookingId, fn(Builder $q) => $q->where('id', '!=', $excludeBookingId));
    }

    /**
     * Scope: Lọc booking active (chưa hủy)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * Scope: Lọc booking cancelled
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Scope: Lọc booking theo status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // ===== ACCESSORS / MUTATORS =====

    /**
     * Accessor: Kiểm tra booking đã qua (check_out đã là quá khứ)
     */
    public function isExpired(): bool
    {
        return $this->check_out->isPast();
    }

    /**
     * Accessor: Kiểm tra booking đã started (check_in đã là quá khứ hoặc hôm nay)
     */
    public function isStarted(): bool
    {
        return $this->check_in->isPast() || $this->check_in->isToday();
    }

    /**
     * Accessor: Số đêm đặt (duration in nights)
     */
    public function getNightsAttribute(): int
    {
        return $this->check_out->diffInDays($this->check_in);
    }

    /**
     * Accessor: Kiểm tra ngày cho phép (check_out có bằng check_in không - không được)
     */
    public function isValidDateRange(): bool
    {
        return $this->check_in->lessThan($this->check_out);
    }

    /**
     * Scope: Lấy lock FOR UPDATE trên các booking trùng
     * 
     * Dùng pessimistic locking để đảm bảo transaction safety
     * DB sẽ lock các row matching query này, ngăn transaction khác sửa
     */
    public function scopeWithLock(Builder $query): Builder
    {
        return $query->lockForUpdate();
    }

    // ===== SOFT DELETE METHODS =====

    /**
     * Get the user who deleted the booking.
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Soft delete with audit trail - records who deleted and when.
     * 
     * @param int|null $deletedByUserId User ID who performed deletion
     * @return bool
     */
    public function softDeleteWithAudit(?int $deletedByUserId = null): bool
    {
        $this->deleted_by = $deletedByUserId ?? auth()->id();
        $this->save();
        
        return $this->delete();
    }

    /**
     * Restore a soft deleted booking and clear audit columns.
     * 
     * @return bool
     */
    public function restoreWithAudit(): bool
    {
        $this->deleted_by = null;
        
        return $this->restore();
    }

    /**
     * Scope: Include only soft deleted bookings (for admin trash view).
     */
    public function scopeOnlyTrashed(Builder $query): Builder
    {
        return $query->onlyTrashed();
    }

    /**
     * Scope: Include both active and soft deleted bookings.
     */
    public function scopeWithTrashed(Builder $query): Builder
    {
        return $query->withTrashed();
    }

    /**
     * Check if this booking is soft deleted.
     */
    public function isTrashed(): bool
    {
        return $this->trashed();
    }

    /**
     * Scope: Filter overlapping bookings including soft deleted ones.
     * Use this for historical reports where deleted bookings matter.
     */
    public function scopeOverlappingBookingsIncludingTrashed(
        Builder $query,
        int $roomId,
        $checkIn,
        $checkOut,
        ?int $excludeBookingId = null
    ): Builder {
        $checkIn = $checkIn instanceof Carbon ? $checkIn : Carbon::parse($checkIn);
        $checkOut = $checkOut instanceof Carbon ? $checkOut : Carbon::parse($checkOut);

        return $query
            ->withTrashed()
            ->where('room_id', $roomId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->when($excludeBookingId, fn(Builder $q) => $q->where('id', '!=', $excludeBookingId));
    }
}
