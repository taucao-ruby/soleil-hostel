<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\UpdateBookingRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Validates UpdateBookingRequest rules — M-06 fix:
 * - guest_name min:2
 * - room_id is 'sometimes' (not required on updates)
 */
class UpdateBookingRequestValidationTest extends TestCase
{
    private function rules(): array
    {
        return (new UpdateBookingRequest)->rules();
    }

    public function test_guest_name_requires_minimum_2_characters(): void
    {
        $validator = Validator::make(
            [
                'check_in' => now()->addDay()->format('Y-m-d'),
                'check_out' => now()->addDays(2)->format('Y-m-d'),
                'guest_name' => 'A',
                'guest_email' => 'guest@example.com',
            ],
            $this->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('guest_name', $validator->errors()->toArray());
    }

    public function test_guest_name_passes_with_2_characters(): void
    {
        $validator = Validator::make(
            [
                'check_in' => now()->addDay()->format('Y-m-d'),
                'check_out' => now()->addDays(2)->format('Y-m-d'),
                'guest_name' => 'AB',
                'guest_email' => 'guest@example.com',
            ],
            $this->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_room_id_is_optional_on_update(): void
    {
        $validator = Validator::make(
            [
                'check_in' => now()->addDay()->format('Y-m-d'),
                'check_out' => now()->addDays(2)->format('Y-m-d'),
                'guest_name' => 'John Doe',
                'guest_email' => 'guest@example.com',
            ],
            $this->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_room_id_validated_when_provided(): void
    {
        $validator = Validator::make(
            [
                'room_id' => 'not-an-integer',
                'check_in' => now()->addDay()->format('Y-m-d'),
                'check_out' => now()->addDays(2)->format('Y-m-d'),
                'guest_name' => 'John Doe',
                'guest_email' => 'guest@example.com',
            ],
            $this->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('room_id', $validator->errors()->toArray());
    }

    // ===== PR-3C: Purification symmetry tests =====

    /**
     * Helper: build an UpdateBookingRequest with a real validator attached.
     * Skips DB-dependent rules (room_id exists check) by only applying rules
     * that do not need a database — purification testing, not DB testing.
     */
    private function makeRequest(array $data): UpdateBookingRequest
    {
        $request = UpdateBookingRequest::create('/api/v1/bookings/1', 'PUT', $data);
        $request->setContainer(app());

        $rules = [
            'check_in'    => 'required|date_format:Y-m-d',
            'check_out'   => 'required|date_format:Y-m-d',
            'guest_name'  => 'required|string|min:2|max:255',
            'guest_email' => 'required|email|max:255',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
        $request->setValidator($validator);

        return $request;
    }

    /**
     * PR-3C: update validated() strips XSS from guest_name.
     * Mirrors the sanitization asserted for StoreBookingRequest.
     */
    public function test_update_booking_validated_strips_xss_from_guest_name(): void
    {
        $request = $this->makeRequest([
            'check_in'    => now()->addDay()->format('Y-m-d'),
            'check_out'   => now()->addDays(2)->format('Y-m-d'),
            'guest_name'  => '<script>alert("xss")</script>John Doe',
            'guest_email' => 'guest@example.com',
        ]);

        $validated = $request->validated();

        $this->assertIsArray($validated);
        $this->assertStringNotContainsString('<script>', $validated['guest_name']);
        $this->assertStringContainsString('John Doe', $validated['guest_name']);
    }

    /**
     * PR-3C: domain-sensitive fields (dates, email) are NOT transformed.
     * Purification must only touch guest_name.
     */
    public function test_update_booking_validated_does_not_alter_domain_fields(): void
    {
        $checkIn  = now()->addDay()->format('Y-m-d');
        $checkOut = now()->addDays(3)->format('Y-m-d');
        $email    = 'guest@example.com';

        $request = $this->makeRequest([
            'check_in'    => $checkIn,
            'check_out'   => $checkOut,
            'guest_name'  => 'Safe Name',
            'guest_email' => $email,
        ]);

        $validated = $request->validated();

        $this->assertEquals($checkIn,  $validated['check_in']);
        $this->assertEquals($checkOut, $validated['check_out']);
        $this->assertEquals($email,    $validated['guest_email']);
        $this->assertEquals('Safe Name', $validated['guest_name']);
    }

    /**
     * PR-3C: validated($key) returns a single value without purification side-effects.
     */
    public function test_update_booking_validated_with_key_returns_single_value(): void
    {
        $request = $this->makeRequest([
            'check_in'    => now()->addDay()->format('Y-m-d'),
            'check_out'   => now()->addDays(2)->format('Y-m-d'),
            'guest_name'  => 'Alice',
            'guest_email' => 'alice@example.com',
        ]);

        $name = $request->validated('guest_name');
        $this->assertEquals('Alice', $name);
    }
}
