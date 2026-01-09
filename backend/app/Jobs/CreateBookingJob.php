<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\CreateBookingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * CreateBookingJob - Queue job để tạo booking với auto-retry
 * 
 * Use case: Nếu API request nhận deadlock exception quá nhiều lần,
 * thay vì return 500 ngay, có thể queue job để retry sau.
 * 
 * Cách dùng:
 * ```
 * CreateBookingJob::dispatch(
 *     userId: $user->id,
 *     roomId: 1,
 *     checkIn: '2025-12-01',
 *     checkOut: '2025-12-05',
 *     guestName: 'John',
 *     guestEmail: 'john@example.com'
 * );
 * ```
 * 
 * Hoặc dùng ở controller khi detect tải cao:
 * ```
 * if ($attemptCount > 3) {
 *     CreateBookingJob::dispatch(...);
 *     return response()->json([
 *         'message' => 'Booking request queued, will be processed soon'
 *     ], 202);
 * }
 * ```
 */
class CreateBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Retry 3 lần nếu fail
    public int $tries = 3;

    // Backup queue
    public string $queue = 'bookings';

    public function __construct(
        private int $userId,
        private int $roomId,
        private string $checkIn,
        private string $checkOut,
        private string $guestName,
        private string $guestEmail,
        private array $additionalData = [],
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CreateBookingService $bookingService): void
    {
        // Get user
        $user = User::find($this->userId);
        if (!$user) {
            throw new RuntimeException("User not found: {$this->userId}");
        }

        // Tạo booking qua service (có pessimistic locking)
        $booking = $bookingService->create(
            roomId: $this->roomId,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            guestName: $this->guestName,
            guestEmail: $this->guestEmail,
            userId: $this->userId,
            additionalData: $this->additionalData,
        );

        // Log success
        \Log::info('Booking created via job', [
            'booking_id' => $booking->id,
            'user_id' => $this->userId,
            'room_id' => $this->roomId,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
        ]);
    }

    /**
     * Handle job failure.
     * 
     * Được gọi khi job fail sau tất cả retry
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('Booking job failed', [
            'user_id' => $this->userId,
            'room_id' => $this->roomId,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Note: Notifications are handled by BookingCreated event listeners
        // No need to manually send emails here
    }
}
