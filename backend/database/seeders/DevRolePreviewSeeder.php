<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Location;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DevRolePreviewSeeder extends Seeder
{
    private const PASSWORD = 'P@ssworD!123';

    public function run(): void
    {
        $rooms = $this->ensurePreviewRooms();

        $admin = $this->upsertUser(
            name: 'Preview Admin',
            email: 'admin@soleil.test',
            role: UserRole::ADMIN,
            verified: true,
        );

        $moderator = $this->upsertUser(
            name: 'Preview Moderator',
            email: 'moderator@soleil.test',
            role: UserRole::MODERATOR,
            verified: true,
        );

        $guest = $this->upsertUser(
            name: 'Preview User',
            email: 'user@soleil.test',
            role: UserRole::USER,
            verified: true,
        );

        $this->upsertUser(
            name: 'Preview Unverified User',
            email: 'pending@soleil.test',
            role: UserRole::USER,
            verified: false,
        );

        $this->upsertBooking(
            reference: 'preview-user-confirmed@soleil.test',
            user: $guest,
            room: $rooms[0],
            status: BookingStatus::CONFIRMED,
            checkIn: Carbon::today()->addDays(7),
            checkOut: Carbon::today()->addDays(9),
            amount: 480000,
        );

        $this->upsertBooking(
            reference: 'preview-moderator-pending@soleil.test',
            user: $moderator,
            room: $rooms[1],
            status: BookingStatus::PENDING,
            checkIn: Carbon::today()->addDays(10),
            checkOut: Carbon::today()->addDays(12),
            amount: 520000,
        );

        $this->upsertBooking(
            reference: 'preview-user-cancelled@soleil.test',
            user: $guest,
            room: $rooms[2],
            status: BookingStatus::CANCELLED,
            checkIn: Carbon::today()->addDays(14),
            checkOut: Carbon::today()->addDays(16),
            amount: 430000,
            cancelledBy: $admin,
            cancelledAt: Carbon::today()->subDay(),
            cancellationReason: 'Preview cancellation by admin',
        );

        $this->upsertTrashedBooking(
            reference: 'preview-trashed-booking@soleil.test',
            user: $guest,
            room: $rooms[0],
            deletedBy: $admin,
            checkIn: Carbon::today()->addDays(20),
            checkOut: Carbon::today()->addDays(22),
            amount: 610000,
        );

        ContactMessage::query()->updateOrCreate(
            ['email' => 'traveler@example.com', 'subject' => 'Late check-in preview'],
            [
                'name' => 'Preview Guest',
                'message' => 'I may arrive after 10 PM. Can the front desk help me check in?',
                'read_at' => null,
            ]
        );

        if ($this->command !== null) {
            $this->command->info('Preview role accounts are ready:');
            $this->command->line('  admin@soleil.test / password');
            $this->command->line('  moderator@soleil.test / password');
            $this->command->line('  user@soleil.test / password');
            $this->command->line('  pending@soleil.test / password');
        }
    }

    /**
     * @return array<int, Room>
     */
    private function ensurePreviewRooms(): array
    {
        $location = Location::query()->first() ?? Location::factory()->inHue()->create([
            'name' => 'Preview Location',
            'slug' => 'preview-location',
        ]);

        $existingRooms = Room::query()
            ->where('location_id', $location->id)
            ->orderBy('id')
            ->take(3)
            ->get();

        if ($existingRooms->count() < 3) {
            $missingCount = 3 - $existingRooms->count();

            Room::factory()
                ->count($missingCount)
                ->available()
                ->forLocation($location)
                ->create();

            $existingRooms = Room::query()
                ->where('location_id', $location->id)
                ->orderBy('id')
                ->take(3)
                ->get();
        }

        return $existingRooms->values()->all();
    }

    private function upsertUser(
        string $name,
        string $email,
        UserRole $role,
        bool $verified,
    ): User {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => self::PASSWORD,
                'role' => $role,
                'email_verified_at' => $verified ? now() : null,
            ]
        );
    }

    private function upsertBooking(
        string $reference,
        User $user,
        Room $room,
        BookingStatus $status,
        Carbon $checkIn,
        Carbon $checkOut,
        int $amount,
        ?User $cancelledBy = null,
        ?Carbon $cancelledAt = null,
        ?string $cancellationReason = null,
    ): Booking {
        return Booking::query()->updateOrCreate(
            ['guest_email' => $reference],
            [
                'user_id' => $user->id,
                'room_id' => $room->id,
                'location_id' => $room->location_id,
                'guest_name' => $user->name,
                'guest_email' => $reference,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'status' => $status,
                'amount' => $amount,
                'cancelled_by' => $cancelledBy?->id,
                'cancelled_at' => $cancelledAt,
                'cancellation_reason' => $cancellationReason,
                'deleted_by' => null,
            ]
        );
    }

    private function upsertTrashedBooking(
        string $reference,
        User $user,
        Room $room,
        User $deletedBy,
        Carbon $checkIn,
        Carbon $checkOut,
        int $amount,
    ): void {
        $booking = Booking::withTrashed()->firstOrNew(['guest_email' => $reference]);

        $booking->fill([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'location_id' => $room->location_id,
            'guest_name' => $user->name,
            'guest_email' => $reference,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'status' => BookingStatus::CONFIRMED,
            'amount' => $amount,
            'deleted_by' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);

        $booking->save();

        if (! $booking->trashed()) {
            $booking->softDeleteWithAudit($deletedBy->id);

            return;
        }

        $booking->deleted_by = $deletedBy->id;
        $booking->save();
    }
}
