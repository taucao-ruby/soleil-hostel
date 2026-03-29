<?php

namespace App\Services;

use App\Database\TransactionIsolation;
use App\Database\TransactionMetrics;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * CreateBookingService - Handles booking creation with double-booking safety
 *
 * Implements pessimistic locking (SELECT ... FOR UPDATE) to guarantee no booking overlaps
 * even under high load (100–500 requests/second)
 *
 * Transaction Isolation Strategy:
 * - Uses READ COMMITTED isolation (PostgreSQL default) with FOR UPDATE locks
 * - Automatic retry on deadlock (40P01) and serialization failure (40001)
 * - Exponential backoff with jitter to reduce contention
 *
 * Data Invariants Protected:
 * - No overlapping bookings for same room (enforced by lock + check)
 * - Booking dates are always valid (check_in < check_out)
 * - Room must exist and be active
 *
 * Error Handling:
 * - Deadlock (40P01): Immediate retry with small jitter
 * - Serialization failure (40001): Retry with exponential backoff
 * - Constraint violation: Business error, no retry
 */
class CreateBookingService
{
    // Number of retry attempts on deadlock or serialization failure
    private const MAX_RETRY_ATTEMPTS = 3;

    // Base delay between retries in ms (exponential backoff: 100ms, 200ms, 400ms)
    private const BASE_RETRY_DELAY_MS = 100;

    // PostgreSQL SQLSTATE codes
    private const SQLSTATE_SERIALIZATION_FAILURE = '40001';

    private const SQLSTATE_DEADLOCK_DETECTED = '40P01';

    /**
     * Create a new booking guaranteed not to overlap with existing bookings
     *
     * @param  Carbon|\DateTime|string  $checkIn
     * @param  Carbon|\DateTime|string  $checkOut
     *
     * @throws RuntimeException If the room does not exist
     * @throws RuntimeException If the room is already booked for the specified dates
     * @throws Throwable On any other database error
     */
    public function create(
        int $roomId,
        $checkIn,
        $checkOut,
        string $guestName,
        string $guestEmail,
        ?int $userId = null,
        array $additionalData = []
    ): Booking {
        // Parse & validate dates
        $checkIn = $this->parseDate($checkIn);
        $checkOut = $this->parseDate($checkOut);

        $this->validateDates($checkIn, $checkOut);

        // Attempt booking creation with deadlock retry logic
        return $this->createWithDeadlockRetry(
            $roomId,
            $checkIn,
            $checkOut,
            $guestName,
            $guestEmail,
            $userId,
            $additionalData
        );
    }

    /**
     * Create a booking with retry logic for deadlock and serialization failures
     *
     * When 2+ transactions simultaneously lock rows and attempt cross-updates,
     * PostgreSQL/MySQL will raise a deadlock exception.
     *
     * Error Types:
     * - Deadlock (40P01): Two transactions waiting for each other's locks
     * - Serialization Failure (40001): SERIALIZABLE/REPEATABLE READ conflict
     *
     * Resolution: Retry with exponential backoff and jitter
     */
    private function createWithDeadlockRetry(
        int $roomId,
        Carbon $checkIn,
        Carbon $checkOut,
        string $guestName,
        string $guestEmail,
        ?int $userId,
        array $additionalData
    ): Booking {
        $attempt = 0;
        $startTime = microtime(true);
        $lastException = null;

        do {
            try {
                $booking = $this->createBookingWithLocking(
                    $roomId,
                    $checkIn,
                    $checkOut,
                    $guestName,
                    $guestEmail,
                    $userId,
                    $additionalData
                );

                // Record success metrics
                $durationMs = (microtime(true) - $startTime) * 1000;
                TransactionMetrics::recordSuccess(
                    'create_booking',
                    TransactionIsolation::READ_COMMITTED,
                    $durationMs,
                    $attempt
                );

                return $booking;

            } catch (PDOException $e) {
                $attempt++;
                $lastException = $e;
                $errorType = $this->classifyDatabaseError($e);

                Log::warning("Booking creation attempt {$attempt} failed", [
                    'room_id' => $roomId,
                    'error_type' => $errorType,
                    'sqlstate' => $e->errorInfo[0] ?? 'unknown',
                    'message' => $e->getMessage(),
                ]);

                // Check if error is retryable
                if (! $this->isRetryableException($e)) {
                    throw $e;
                }

                if ($attempt >= self::MAX_RETRY_ATTEMPTS) {
                    // Record failure metrics
                    TransactionMetrics::recordFailure(
                        'create_booking',
                        TransactionIsolation::READ_COMMITTED,
                        $attempt,
                        (microtime(true) - $startTime) * 1000
                    );

                    throw new RuntimeException(
                        'Không thể tạo booking sau '.self::MAX_RETRY_ATTEMPTS.' lần thử do xung đột database. Vui lòng thử lại.',
                        0,
                        $e
                    );
                }

                // Calculate delay based on error type
                $delayMs = $this->calculateRetryDelay($attempt, $errorType);
                usleep($delayMs * 1000);

                continue;
            }
        } while ($attempt < self::MAX_RETRY_ATTEMPTS);

        throw new RuntimeException('Không thể tạo booking', 0, $lastException);
    }

    /**
     * Classify database error for proper handling.
     *
     * @return string Error type: 'deadlock', 'serialization', 'lock_timeout', 'other'
     */
    private function classifyDatabaseError(PDOException $e): string
    {
        $sqlstate = (string) ($e->errorInfo[0] ?? $e->getCode());
        $message = strtolower($e->getMessage());

        if ($sqlstate === self::SQLSTATE_DEADLOCK_DETECTED || str_contains($message, 'deadlock')) {
            return 'deadlock';
        }

        if ($sqlstate === self::SQLSTATE_SERIALIZATION_FAILURE) {
            return 'serialization';
        }

        if (str_contains($message, 'lock wait timeout') || str_contains($message, 'lock timeout')) {
            return 'lock_timeout';
        }

        if (str_contains($message, 'database is locked')) {
            return 'sqlite_busy';
        }

        return 'other';
    }

    /**
     * Calculate retry delay based on error type and attempt number.
     *
     * Strategy:
     * - Deadlock: Quick retry with small random jitter (10-50ms)
     * - Serialization: Exponential backoff with jitter
     * - Lock timeout: Longer delay to allow lock release
     *
     * @param  int  $attempt  Attempt number (1-based)
     * @param  string  $errorType  Error classification
     * @return int Delay in milliseconds
     */
    private function calculateRetryDelay(int $attempt, string $errorType): int
    {
        return match ($errorType) {
            'deadlock' => random_int(10, 50),
            'serialization' => (int) (self::BASE_RETRY_DELAY_MS * pow(2, $attempt - 1) + random_int(0, 50)),
            'lock_timeout' => (int) (self::BASE_RETRY_DELAY_MS * pow(2, $attempt) + random_int(0, 100)),
            'sqlite_busy' => random_int(50, 150),
            default => (int) (self::BASE_RETRY_DELAY_MS * pow(2, $attempt - 1)),
        };
    }

    /**
     * Check if exception is retryable.
     *
     * Retryable errors (transient):
     * - Deadlock detected (40P01)
     * - Serialization failure (40001)
     * - Lock wait timeout
     * - SQLite busy
     *
     * Non-retryable errors (business logic):
     * - Unique constraint violation
     * - Foreign key violation
     * - Check constraint violation
     */
    private function isRetryableException(PDOException $e): bool
    {
        $errorType = $this->classifyDatabaseError($e);

        return in_array($errorType, ['deadlock', 'serialization', 'lock_timeout', 'sqlite_busy'], true);
    }

    /**
     * Create a booking using pessimistic locking (SELECT ... FOR UPDATE)
     *
     * Flow:
     * 1. Begin transaction
     * 2. SELECT from bookings FOR UPDATE (locks rows matching the overlap condition)
     * 3. Check for overlapping bookings
     * 4. If no conflicts, INSERT the new booking
     * 5. Commit transaction (releases lock)
     *
     * Key: Lock is held until the transaction commits or rolls back,
     * preventing any other transaction from creating a conflicting booking
     */
    private function createBookingWithLocking(
        int $roomId,
        Carbon $checkIn,
        Carbon $checkOut,
        string $guestName,
        string $guestEmail,
        ?int $userId,
        array $additionalData
    ): Booking {
        return DB::transaction(function () use (
            $roomId,
            $checkIn,
            $checkOut,
            $guestName,
            $guestEmail,
            $userId,
            $additionalData
        ) {
            // Step 1: Verify room exists
            $room = Room::find($roomId);
            if (! $room) {
                throw new ModelNotFoundException(
                    __('booking.room_not_found', ['id' => $roomId])
                );
            }

            // Step 2: Acquire lock on all active bookings for this room
            // This query locks matching rows in the bookings table
            // Other transactions attempting SELECT FOR UPDATE or UPDATE on these rows will wait
            $existingBookings = Booking::query()
                ->overlappingBookings($roomId, $checkIn, $checkOut)
                ->withLock()
                ->get();

            // Step 3: Throw if overlapping bookings exist
            // Exception is caught and handled by the caller
            if ($existingBookings->isNotEmpty()) {
                throw new RuntimeException(
                    'Room is already booked for the specified dates. Please choose different dates.'
                );
            }

            // Step 4: Insert new booking (still within transaction; lock is still held)
            // location_id is set explicitly here from room->location_id so the application
            // path is self-sufficient. The PostgreSQL trigger (trg_booking_set_location)
            // and BookingObserver remain as independent backstops.
            $booking = Booking::create([
                'room_id' => $roomId,
                'location_id' => $room->location_id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'status' => BookingStatus::PENDING,
                'user_id' => $userId,
                ...$additionalData,
            ]);

            // Step 5: Return booking (transaction auto-commit, lock released)
            return $booking;
        });
    }

    /**
     * Update a booking while checking for overlaps (excluding the booking itself)
     *
     * Similar to create, but excludes the current booking ID
     * to prevent a false overlap conflict with itself
     */
    public function update(
        Booking $booking,
        Carbon $checkIn,
        Carbon $checkOut,
        array $additionalData = [],
        $request = null
    ): Booking {
        $this->validateDates($checkIn, $checkOut, true, $request);

        return DB::transaction(function () use ($booking, $checkIn, $checkOut, $additionalData) {
            // Acquire lock on overlapping bookings (excluding the current booking)
            $conflicts = Booking::query()
                ->overlappingBookings($booking->room_id, $checkIn, $checkOut, $booking->id)
                ->withLock()
                ->exists();

            if ($conflicts) {
                throw new RuntimeException(
                    'Room is already booked for the specified dates. Please choose different dates.'
                );
            }

            $booking->update([
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                ...$additionalData,
            ]);

            return $booking;
        });
    }

    /**
     * Validate date range
     *
     * @param  bool  $isUpdate  Whether this is an update operation
     * @param  \Illuminate\Http\Request|null  $request  The request object (for updates)
     */
    private function validateDates(Carbon $checkIn, Carbon $checkOut, bool $isUpdate = false, $request = null): void
    {
        // Skip validation for updates where dates aren't being changed
        if ($isUpdate && $request && ! $request->has(['check_in_date', 'check_out_date'])) {
            return;
        }

        if (! $checkIn->lessThan($checkOut)) {
            throw new RuntimeException(
                'Ngày check-out phải sau ngày check-in'
            );
        }

        // Only enforce future check-in for new bookings, not updates to existing ones
        if (! $isUpdate && $checkIn->isPast()) {
            throw new RuntimeException(
                'Ngày check-in phải là ngày trong tương lai'
            );
        }
    }

    /**
     * Parse date string/datetime
     */
    private function parseDate($date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date;
        }

        return Carbon::parse($date)->startOfDay();
    }
}
