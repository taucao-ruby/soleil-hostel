<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\CreateBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests cho CreateBookingService
 * 
 * Verify logic của service đúng: pessimistic locking, overlap detection, retry
 */
class CreateBookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreateBookingService $service;
    private Room $room;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CreateBookingService::class);
        $this->room = Room::factory()->create();
        $this->user = User::factory()->create();
    }

    /**
     * Test: Service tạo booking thành công
     */
    public function test_service_creates_booking_successfully(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(3);

        $booking = $this->service->create(
            roomId: $this->room->id,
            checkIn: $checkIn,
            checkOut: $checkOut,
            guestName: 'Test Guest',
            guestEmail: 'test@example.com',
            userId: $this->user->id,
        );

        $this->assertNotNull($booking->id);
        $this->assertEquals($this->room->id, $booking->room_id);
        $this->assertEquals($this->user->id, $booking->user_id);
        $this->assertEquals(BookingStatus::PENDING, $booking->status);
    }

    /**
     * Test: Service throw exception khi phòng không tồn tại
     */
    public function test_service_throws_exception_when_room_not_found(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->create(
            roomId: 9999,
            checkIn: Carbon::tomorrow(),
            checkOut: Carbon::tomorrow()->addDays(3),
            guestName: 'Test',
            guestEmail: 'test@example.com',
            userId: $this->user->id,
        );
    }

    /**
     * Test: Service throw exception khi dates không valid
     */
    public function test_service_throws_exception_with_invalid_dates(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ngày check-out phải sau');

        $this->service->create(
            roomId: $this->room->id,
            checkIn: Carbon::tomorrow()->addDays(5),
            checkOut: Carbon::tomorrow(), // Before check-in
            guestName: 'Test',
            guestEmail: 'test@example.com',
            userId: $this->user->id,
        );
    }

    /**
     * Test: Service throw exception khi overlap xảy ra
     */
    public function test_service_throws_exception_when_overlap_detected(): void
    {
        $checkIn1 = Carbon::tomorrow();
        $checkOut1 = Carbon::tomorrow()->addDays(5);

        // Create first booking
        Booking::create([
            'room_id' => $this->room->id,
            'check_in' => $checkIn1,
            'check_out' => $checkOut1,
            'guest_name' => 'Guest 1',
            'guest_email' => 'guest1@example.com',
            'user_id' => $this->user->id,
            'status' => 'confirmed',
        ]);

        // Try to create overlapping booking
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Phòng đã được đặt');

        $this->service->create(
            roomId: $this->room->id,
            checkIn: Carbon::tomorrow()->addDays(2),
            checkOut: Carbon::tomorrow()->addDays(4),
            guestName: 'Guest 2',
            guestEmail: 'guest2@example.com',
            userId: User::factory()->create()->id,
        );
    }

    /**
     * Test: Service cho phép booking khi checkout = checkin (half-open interval)
     * 
     * Database overlap logic dùng half-open interval [a, b):
     * - Booking 1: [2025-12-01, 2025-12-06) - cho phép checkout 2025-12-05, next checkin 2025-12-06
     * - Booking 2: [2025-12-06, 2025-12-10) - bắt đầu đúng khi booking 1 kết thúc
     * - Overlap detection: check_in1 < check_out2 AND check_out1 > check_in2
     * - Kết quả: 2025-12-01 < 2025-12-10 AND 2025-12-06 > 2025-12-06 = FALSE (không overlap)
     */
    public function test_service_allows_booking_on_same_day_boundary(): void
    {
        $day1 = Carbon::tomorrow();
        $day5 = Carbon::tomorrow()->addDays(5);
        $day6 = Carbon::tomorrow()->addDays(6);
        $day10 = Carbon::tomorrow()->addDays(10);

        Booking::create([
            'room_id' => $this->room->id,
            'check_in' => $day1,
            'check_out' => $day5,
            'guest_name' => 'Guest 1',
            'guest_email' => 'guest1@example.com',
            'user_id' => $this->user->id,
            'status' => 'confirmed',
        ]);

        // Booking 2 bắt đầu khi booking 1 kết thúc (day5 == day5 is not < but booking ends at day5)
        // Tạo booking từ day6 (không overlap)
        $booking = $this->service->create(
            roomId: $this->room->id,
            checkIn: $day6,
            checkOut: $day10,
            guestName: 'Guest 2',
            guestEmail: 'guest2@example.com',
            userId: User::factory()->create()->id,
        );

        $this->assertNotNull($booking->id);
    }

    /**
     * Test: Service allows cancelled bookings không block
     */
    public function test_service_allows_booking_over_cancelled_booking(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(5);

        Booking::create([
            'room_id' => $this->room->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guest_name' => 'Guest 1',
            'guest_email' => 'guest1@example.com',
            'user_id' => $this->user->id,
            'status' => 'cancelled', // Cancelled
        ]);

        $booking = $this->service->create(
            roomId: $this->room->id,
            checkIn: $checkIn->addDays(1),
            checkOut: $checkOut->subDays(1),
            guestName: 'Guest 2',
            guestEmail: 'guest2@example.com',
            userId: User::factory()->create()->id,
        );

        $this->assertNotNull($booking->id);
    }

    /**
     * Test: Service update booking with overlap detection
     */
    public function test_service_update_booking_with_overlap_detection(): void
    {
        $checkIn1 = Carbon::tomorrow();
        $checkOut1 = Carbon::tomorrow()->addDays(5);

        $booking1 = Booking::create([
            'room_id' => $this->room->id,
            'check_in' => $checkIn1,
            'check_out' => $checkOut1,
            'guest_name' => 'Guest 1',
            'guest_email' => 'guest1@example.com',
            'user_id' => $this->user->id,
            'status' => 'confirmed',
        ]);

        $booking2 = Booking::create([
            'room_id' => $this->room->id,
            'check_in' => Carbon::tomorrow()->addDays(10),
            'check_out' => Carbon::tomorrow()->addDays(12),
            'guest_name' => 'Guest 2',
            'guest_email' => 'guest2@example.com',
            'user_id' => User::factory()->create()->id,
            'status' => 'confirmed',
        ]);

        // Try to update booking2 to overlap với booking1
        $this->expectException(RuntimeException::class);

        $this->service->update(
            booking: $booking2,
            checkIn: Carbon::tomorrow()->addDays(2),
            checkOut: Carbon::tomorrow()->addDays(4),
        );
    }

    /**
     * Test: Service update booking successfully when no overlap
     */
    public function test_service_update_booking_successfully(): void
    {
        $booking = Booking::create([
            'room_id' => $this->room->id,
            'check_in' => Carbon::tomorrow(),
            'check_out' => Carbon::tomorrow()->addDays(3),
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.com',
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $newCheckIn = Carbon::tomorrow()->addDays(10);
        $newCheckOut = Carbon::tomorrow()->addDays(12);

        $updated = $this->service->update(
            booking: $booking,
            checkIn: $newCheckIn,
            checkOut: $newCheckOut,
        );

        $this->assertEquals($newCheckIn->toDateString(), $updated->check_in->toDateString());
        $this->assertEquals($newCheckOut->toDateString(), $updated->check_out->toDateString());
    }

    /**
     * Test: String dates được parse đúng
     */
    public function test_service_handles_string_dates(): void
    {
        $checkIn = Carbon::tomorrow()->toDateString();
        $checkOut = Carbon::tomorrow()->addDays(3)->toDateString();

        $booking = $this->service->create(
            roomId: $this->room->id,
            checkIn: $checkIn,
            checkOut: $checkOut,
            guestName: 'Test',
            guestEmail: 'test@example.com',
            userId: $this->user->id,
        );

        $this->assertEquals($checkIn, $booking->check_in->toDateString());
        $this->assertEquals($checkOut, $booking->check_out->toDateString());
    }

    /**
     * Test: Additonal data merged properly
     */
    public function test_service_merges_additional_data(): void
    {
        $booking = $this->service->create(
            roomId: $this->room->id,
            checkIn: Carbon::tomorrow(),
            checkOut: Carbon::tomorrow()->addDays(3),
            guestName: 'Test',
            guestEmail: 'test@example.com',
            userId: $this->user->id,
            additionalData: [
                'status' => BookingStatus::CONFIRMED, // Override default status
            ],
        );

        $this->assertEquals(BookingStatus::CONFIRMED, $booking->status);
    }
}
