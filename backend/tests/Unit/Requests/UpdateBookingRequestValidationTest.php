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
        return (new UpdateBookingRequest())->rules();
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
}
