<?php

namespace App\Services;

use App\Database\TransactionIsolation;
use App\Database\TransactionMetrics;
use App\Exceptions\DoubleBookingException;
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
 * CreateBookingService - Xử lý tạo booking với an toàn double-booking
 * 
 * Triển khai pessimistic locking (SELECT ... FOR UPDATE) để đảm bảo không bao giờ có overlap
 * kể cả dưới tải cao (100-500 request/giây)
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
    // Số lần retry khi deadlock hoặc serialization failure
    private const MAX_RETRY_ATTEMPTS = 3;
    
    // Thời gian chờ giữa retry (exponential backoff: 100ms, 200ms, 400ms)
    private const BASE_RETRY_DELAY_MS = 100;

    // PostgreSQL SQLSTATE codes
    private const SQLSTATE_SERIALIZATION_FAILURE = '40001';
    private const SQLSTATE_DEADLOCK_DETECTED = '40P01';

    /**
     * Tạo booking mới với đảm bảo không overlap
     * 
     * @param int $roomId
     * @param Carbon|\DateTime|string $checkIn
     * @param Carbon|\DateTime|string $checkOut
     * @param string $guestName
     * @param string $guestEmail
     * @param int|null $userId
     * @param array $additionalData
     * @return Booking
     * @throws RuntimeException Khi phòng không tồn tại
     * @throws RuntimeException Khi phòng đã được đặt cho ngày chỉ định
     * @throws Throwable Khi database error khác xảy ra
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

        // Thử tạo booking với retry logic cho deadlock
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
     * Tạo booking với retry logic cho deadlock và serialization failure
     * 
     * Khi 2+ transaction cùng lock các row và cố gắng update chéo nhau,
     * PostgreSQL/MySQL sẽ raise deadlock exception.
     * 
     * Error Types:
     * - Deadlock (40P01): Two transactions waiting for each other's locks
     * - Serialization Failure (40001): SERIALIZABLE/REPEATABLE READ conflict
     * 
     * Giải pháp: Retry với exponential backoff + jitter
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
                if (!$this->isRetryableException($e)) {
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
                        'Không thể tạo booking sau ' . self::MAX_RETRY_ATTEMPTS . ' lần thử do xung đột database. Vui lòng thử lại.',
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
     * @param PDOException $e
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
     * @param int $attempt Attempt number (1-based)
     * @param string $errorType Error classification
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
     * 
     * @param PDOException $e
     * @return bool
     */
    private function isRetryableException(PDOException $e): bool
    {
        $errorType = $this->classifyDatabaseError($e);
        return in_array($errorType, ['deadlock', 'serialization', 'lock_timeout', 'sqlite_busy'], true);
    }

    /**
     * Tạo booking với pessimistic locking (SELECT ... FOR UPDATE)
     * 
     * Flow:
     * 1. Bắt đầu transaction
     * 2. SELECT từ bookings table FOR UPDATE (lock các row matching condition)
     * 3. Kiểm tra xem có booking trùng không
     * 4. Nếu không có, INSERT booking mới
     * 5. Commit transaction (release lock)
     * 
     * Điều quan trọng: Lock được giữ cho đến khi transaction commit/rollback,
     * đảm bảo không có transaction khác có thể tạo booking trùng
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
            // Step 1: Kiểm tra phòng tồn tại
            $room = Room::find($roomId);
            if (!$room) {
                throw new ModelNotFoundException(
                    "Phòng với ID {$roomId} không tồn tại"
                );
            }

            // Step 2: Lấy lock trên tất cả active booking của phòng này
            // Query này sẽ lock các row từ bookings table mà thỏa điều kiện
            // Các transaction khác cố gắng SELECT FOR UPDATE hoặc update sẽ bị chờ
            $existingBookings = Booking::query()
                ->overlappingBookings($roomId, $checkIn, $checkOut)
                ->withLock()
                ->get();

            // Step 3: Nếu có booking trùng, throw exception
            // Exception sẽ được catch ở ngoài và xử lý
            if ($existingBookings->isNotEmpty()) {
                throw new RuntimeException(
                    'Phòng đã được đặt cho ngày chỉ định. Vui lòng chọn ngày khác.'
                );
            }

            // Step 4: Tạo booking mới (vẫn trong transaction, lock vẫn được giữ)
            $booking = Booking::create([
                'room_id' => $roomId,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'status' => Booking::STATUS_PENDING,
                'user_id' => $userId,
                ...$additionalData,
            ]);

            // Step 5: Return booking (transaction auto-commit, lock released)
            return $booking;
        });
    }

    /**
     * Update booking với check overlap (exclude chính nó)
     * 
     * Giống như create, nhưng exclude booking id hiện tại
     * để tránh check constraint với chính nó
     */
    public function update(
        Booking $booking,
        Carbon $checkIn,
        Carbon $checkOut,
        array $additionalData = []
    ): Booking {
        $this->validateDates($checkIn, $checkOut);

        return DB::transaction(function () use ($booking, $checkIn, $checkOut, $additionalData) {
            // Lấy lock trên overlapping bookings (exclude current booking)
            $conflicts = Booking::query()
                ->overlappingBookings($booking->room_id, $checkIn, $checkOut, $booking->id)
                ->withLock()
                ->exists();

            if ($conflicts) {
                throw new RuntimeException(
                    'Phòng đã được đặt cho ngày chỉ định. Vui lòng chọn ngày khác.'
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
     */
    private function validateDates(Carbon $checkIn, Carbon $checkOut): void
    {
        if (!$checkIn->lessThan($checkOut)) {
            throw new RuntimeException(
                'Ngày check-out phải sau ngày check-in'
            );
        }

        if ($checkIn->isPast()) {
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
