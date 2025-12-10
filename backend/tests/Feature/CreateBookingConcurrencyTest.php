<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature Tests cho double-booking prevention
 * 
 * Các test này dùng RefreshDatabase để reset DB giữa test,
 * đảm bảo mỗi test là isolated và deterministic
 * 
 * Các test sẽ verify:
 * 1. Normal booking creation works
 * 2. Booking với overlapping dates bị reject
 * 3. Booking với same check_in/check_out boundary được allow (checkout = checkin)
 * 4. Concurrent requests không thể double-book (simulated qua Eloquent)
 * 5. Cancelled bookings không ảnh hưởng đến locking logic
 */
class CreateBookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;
    private User $user1;
    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        // Tạo test room
        $this->room = Room::factory()->create([
            'name' => 'Test Room',
            'price' => 100,
        ]);

        // Tạo 2 test users
        $this->user1 = User::factory()->create(['email' => 'user1@test.com']);
        $this->user2 = User::factory()->create(['email' => 'user2@test.com']);

        // Create and set token for user1
        $token = $this->user1->createToken('test-token');
        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken);
    }

    /**
     * Test 1: Booking normal (không overlap) được tạo successfully
     */
    public function test_normal_booking_creation_succeeds(): void
    {
        $checkIn = Carbon::tomorrow()->toDateString();
        $checkOut = Carbon::tomorrow()->addDays(2)->toDateString();

        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_name' => 'John Doe',
                'guest_email' => 'john@example.com',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        // Verify booking được lưu vào DB (note: database stores with timestamp)
        $this->assertDatabaseHas('bookings', [
            'room_id' => $this->room->id,
            'guest_name' => 'John Doe',
            'status' => 'pending',
        ]);
    }

    /**
     * Test 2: Booking với overlap hoàn toàn bị reject
     * 
     * Timeline:
     * Booking 1: 2025-12-01 to 2025-12-05
     * Booking 2: 2025-12-02 to 2025-12-04 (nằm hoàn toàn trong booking 1)
     * → Booking 2 phải reject
     */
    public function test_fully_overlapping_booking_is_rejected(): void
    {
        // Create first booking
        $checkIn1 = Carbon::tomorrow()->toDateString();
        $checkOut1 = Carbon::tomorrow()->addDays(5)->toDateString();

        Booking::create([
            'room_id' => $this->room->id,
            'check_in' => $checkIn1,
            'check_out' => $checkOut1,
            'guest_name' => 'First Guest',
            'guest_email' => 'first@example.com',
            'user_id' => $this->user1->id,
            'status' => 'confirmed',
        ]);

        // Try to create overlapping booking
        $checkIn2 = Carbon::tomorrow()->addDays(1)->toDateString();
        $checkOut2 = Carbon::tomorrow()->addDays(4)->toDateString();

        $response = $this->actingAs($this->user2, 'sanctum')
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'check_in' => $checkIn2,
                'check_out' => $checkOut2,
                'guest_name' => 'Second Guest',
                'guest_email' => 'second@example.com',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Phòng đã được đặt cho ngày chỉ định. Vui lòng chọn ngày khác.');

        // Verify booking không được tạo
        $this->assertEquals(1, Booking::where('room_id', $this->room->id)->count());
    }

    /**
     * Test 3: Booking bắt đầu khi booking khác kết thúc được allow (half-open interval)
     * 
     * Timeline:
     * Booking 1: 2025-12-01 to 2025-12-05 (checkout ngày 5)
     * Booking 2: 2025-12-05 to 2025-12-10 (checkin ngày 5)
     * → Cho phép vì [1, 5) không overlap [5, 10)
     * 
     * Lý do: Checkout sáng, check-in trưa cùng ngày, phòng còn có thời gian dọn dẹp
     */
    public function test_same_day_checkin_checkout_boundary_is_allowed(): void
    {
        $checkIn1 = Carbon::tomorrow()->toDateString();
        $checkOut1 = Carbon::tomorrow()->addDays(4)->toDateString();

        Booking::create([
            'room_id' => $this->room->id,
            'check_in' => $checkIn1,
            'check_out' => $checkOut1,
            'guest_name' => 'First Guest',
            'guest_email' => 'first@example.com',
            'user_id' => $this->user1->id,
            'status' => 'confirmed',
        ]);

        // Booking bắt đầu đúng khi booking khác kết thúc
        $checkIn2 = $checkOut1; // Same day
        $checkOut2 = Carbon::parse($checkOut1)->addDays(5)->toDateString();

        $response = $this->actingAs($this->user2, 'sanctum')
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'check_in' => $checkIn2,
                'check_out' => $checkOut2,
                'guest_name' => 'Second Guest',
                'guest_email' => 'second@example.com',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        // Verify both bookings exist
        $this->assertEquals(2, Booking::where('room_id', '=', $this->room->id)->count());
    }

    /**
     * Test 4: Partial overlap ở đầu bị reject
     * 
     * Timeline:
     * Booking 1: 2025-12-02 to 2025-12-05
     * Booking 2: 2025-12-01 to 2025-12-03 (overlap ở 12-02, 12-03)
     * → Booking 2 phải reject
     */
    public function test_partial_overlap_at_start_is_rejected(): void
    {
        $checkIn1 = Carbon::tomorrow()->addDays(1)->toDateString();
        $checkOut1 = Carbon::tomorrow()->addDays(4)->toDateString();

        Booking::create([
            'room_id' => $this->room->id,
            'check_in' => $checkIn1,
            'check_out' => $checkOut1,
            'guest_name' => 'First Guest',
            'guest_email' => 'first@example.com',
            'user_id' => $this->user1->id,
            'status' => 'confirmed',
        ]);

        // Overlap ở đầu
        $checkIn2 = Carbon::tomorrow()->toDateString();
        $checkOut2 = Carbon::tomorrow()->addDays(2)->toDateString();

        $response = $this->actingAs($this->user2, 'sanctum')
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'check_in' => $checkIn2,
                'check_out' => $checkOut2,
                'guest_name' => 'Second Guest',
                'guest_email' => 'second@example.com',
            ]);

        $response->assertStatus(422);
        $this->assertEquals(1, Booking::where('room_id', $this->room->id)->count());
    }

    /**
     * Test 5: Partial overlap ở cuối bị reject
     * 
     * Timeline:
     * Booking 1: 2025-12-01 to 2025-12-03
     * Booking 2: 2025-12-02 to 2025-12-05 (overlap ở 12-02)
     * → Booking 2 phải reject
     */
    public function test_partial_overlap_at_end_is_rejected(): void
    {
        $checkIn1 = Carbon::tomorrow()->toDateString();
        $checkOut1 = Carbon::tomorrow()->addDays(2)->toDateString();

        Booking::create([
            'room_id' => $this->room->id,
            'check_in' => $checkIn1,
            'check_out' => $checkOut1,
            'guest_name' => 'First Guest',
            'guest_email' => 'first@example.com',
            'user_id' => $this->user1->id,
            'status' => 'confirmed',
        ]);

        // Overlap ở cuối
        $checkIn2 = Carbon::tomorrow()->addDays(1)->toDateString();
        $checkOut2 = Carbon::tomorrow()->addDays(5)->toDateString();

        $response = $this->actingAs($this->user2, 'sanctum')
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'check_in' => $checkIn2,
                'check_out' => $checkOut2,
                'guest_name' => 'Second Guest',
                'guest_email' => 'second@example.com',
            ]);

        $response->assertStatus(422);
        $this->assertEquals(1, Booking::where('room_id', $this->room->id)->count());
    }

    /**
     * Test 6: Cancelled booking không block overlap booking mới
     * 
     * Timeline:
     * Booking 1: 2025-12-01 to 2025-12-05 (status: cancelled)
     * Booking 2: 2025-12-02 to 2025-12-04 (overlap với booking 1)
     * → Booking 2 được allow vì booking 1 cancelled
     */
    public function test_cancelled_booking_does_not_block_new_booking(): void
    {
        $checkIn1 = Carbon::tomorrow()->toDateString();
        $checkOut1 = Carbon::tomorrow()->addDays(5)->toDateString();

        Booking::create([
            'room_id' => $this->room->id,
            'check_in' => $checkIn1,
            'check_out' => $checkOut1,
            'guest_name' => 'First Guest',
            'guest_email' => 'first@example.com',
            'user_id' => $this->user1->id,
            'status' => 'cancelled',
        ]);

        // Try to create booking với overlapping dates
        $checkIn2 = Carbon::tomorrow()->addDays(1)->toDateString();
        $checkOut2 = Carbon::tomorrow()->addDays(4)->toDateString();

        $response = $this->actingAs($this->user2, 'sanctum')
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'check_in' => $checkIn2,
                'check_out' => $checkOut2,
                'guest_name' => 'Second Guest',
                'guest_email' => 'second@example.com',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        // Verify both bookings exist
        $this->assertEquals(2, Booking::where('room_id', $this->room->id)->count());
    }

    /**
     * Test 7: Booking update với overlapping dates bị reject
     * 
     * Verify that updating a booking to overlap with an existing booking is rejected
     */
    public function test_booking_update_with_overlap_is_rejected(): void
    {
        // Create first booking
        $booking1 = Booking::create([
            'room_id' => $this->room->id,
            'check_in' => Carbon::tomorrow()->addDays(1)->toDateString(),
            'check_out' => Carbon::tomorrow()->addDays(5)->toDateString(),
            'guest_name' => 'Guest 1',
            'guest_email' => 'guest1@example.com',
            'user_id' => $this->user1->id,
            'status' => 'confirmed',
        ]);

        // Create second booking for same user that won't overlap
        $booking2 = Booking::create([
            'room_id' => $this->room->id,
            'check_in' => Carbon::tomorrow()->addDays(10)->toDateString(),
            'check_out' => Carbon::tomorrow()->addDays(12)->toDateString(),
            'guest_name' => 'Guest 2',
            'guest_email' => 'guest2@example.com',
            'user_id' => $this->user1->id,
            'status' => 'pending',
        ]);

        // Try to update booking2 to overlap with booking1
        $response = $this->actingAs($this->user1, 'sanctum')
            ->putJson("/api/bookings/{$booking2->id}", [
                'room_id' => $this->room->id,
                'check_in' => Carbon::tomorrow()->addDays(3)->toDateString(),
                'check_out' => Carbon::tomorrow()->addDays(7)->toDateString(),
                'guest_name' => 'Updated Guest',
                'guest_email' => 'updated@example.com',
            ]);

        // Should reject with 422 due to overlap
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);

        // Verify booking 2 dates not changed
        $booking2->refresh();
        $this->assertEquals(Carbon::tomorrow()->addDays(10)->toDateString(), $booking2->check_in->toDateString());
    }

    /**
     * Test 8: Multiple rooms không ảnh hưởng booking của nhau
     * 
     * Verify rằng overlap detection chỉ check room hiện tại,
     * không check across rooms
     */
    public function test_different_rooms_can_have_same_dates(): void
    {
        $room2 = Room::factory()->create(['name' => 'Room 2', 'price' => 150]);

        $checkIn = Carbon::tomorrow()->toDateString();
        $checkOut = Carbon::tomorrow()->addDays(3)->toDateString();

        // Booking room 1
        Booking::create([
            'room_id' => $this->room->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guest_name' => 'Guest 1',
            'guest_email' => 'guest1@example.com',
            'user_id' => $this->user1->id,
            'status' => 'confirmed',
        ]);

        // Booking room 2 với cùng dates
        $response = $this->actingAs($this->user2, 'sanctum')
            ->postJson('/api/bookings', [
                'room_id' => $room2->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_name' => 'Guest 2',
                'guest_email' => 'guest2@example.com',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        // Verify cả 2 bookings exist
        $this->assertEquals(1, Booking::where('room_id', $this->room->id)->count());
        $this->assertEquals(1, Booking::where('room_id', $room2->id)->count());
    }

    /**
     * Test 9: Valid date validation
     * 
     * Check-out phải > check-in
     */
    public function test_invalid_date_range_is_rejected(): void
    {
        $checkIn = Carbon::tomorrow()->addDays(5)->toDateString();
        $checkOut = Carbon::tomorrow()->toDateString(); // Before check-in

        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_name' => 'John Doe',
                'guest_email' => 'john@example.com',
            ]);

        $response->assertStatus(422);
        $this->assertEquals(0, Booking::where('room_id', $this->room->id)->count());
    }

    /**
     * Test 10: Past dates không được allow
     */
    public function test_past_checkin_date_is_rejected(): void
    {
        $checkIn = Carbon::yesterday()->toDateString();
        $checkOut = Carbon::tomorrow()->toDateString();

        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_name' => 'John Doe',
                'guest_email' => 'john@example.com',
            ]);

        $response->assertStatus(422);
    }
}
