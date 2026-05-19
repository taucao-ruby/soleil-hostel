<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\DevRolePreviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the dev preview seeder under A-1.
 *
 * Booking::$fillable was shrunk to 5 user-input columns in commit d67b13f.
 * The trusted preview seeder writes state-machine, authorship, payment, and
 * cancellation-audit columns and MUST use forceFill (or direct assignment)
 * to bypass the shrunk mass-assignment surface — otherwise Laravel silently
 * drops those values and preview accounts get bookings with NULL user_id,
 * DB-default status, NULL amount, and missing cancellation audit data.
 *
 * The earlier upsertBooking implementation used Booking::query()->updateOrCreate
 * which respects $fillable; this test pins the post-fix forceFill path.
 */
final class DevRolePreviewSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_seeder_persists_protected_columns_on_active_bookings(): void
    {
        $this->seed(DevRolePreviewSeeder::class);

        $confirmed = Booking::query()
            ->where('guest_email', 'preview-user-confirmed@soleil.test')
            ->firstOrFail();

        $this->assertNotNull($confirmed->user_id, 'confirmed preview booking must carry user_id');
        $this->assertSame(BookingStatus::CONFIRMED, $confirmed->status);
        $this->assertSame(480000, $confirmed->amount);

        $pending = Booking::query()
            ->where('guest_email', 'preview-moderator-pending@soleil.test')
            ->firstOrFail();

        $this->assertNotNull($pending->user_id);
        $this->assertSame(BookingStatus::PENDING, $pending->status);
        $this->assertSame(520000, $pending->amount);
    }

    public function test_preview_seeder_persists_cancellation_audit_columns(): void
    {
        $this->seed(DevRolePreviewSeeder::class);

        $cancelled = Booking::query()
            ->where('guest_email', 'preview-user-cancelled@soleil.test')
            ->firstOrFail();

        $admin = User::query()
            ->where('email', 'admin@soleil.test')
            ->firstOrFail();

        $this->assertSame(BookingStatus::CANCELLED, $cancelled->status);
        $this->assertSame($admin->id, $cancelled->cancelled_by);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('Preview cancellation by admin', $cancelled->cancellation_reason);
        $this->assertSame(430000, $cancelled->amount);
    }

    public function test_preview_seeder_persists_trashed_booking_with_deleted_by(): void
    {
        $this->seed(DevRolePreviewSeeder::class);

        $trashed = Booking::withTrashed()
            ->where('guest_email', 'preview-trashed-booking@soleil.test')
            ->firstOrFail();

        $admin = User::query()
            ->where('email', 'admin@soleil.test')
            ->firstOrFail();

        $this->assertNotNull($trashed->user_id);
        $this->assertSame(BookingStatus::CONFIRMED, $trashed->status);
        $this->assertSame(610000, $trashed->amount);
        $this->assertNotNull($trashed->deleted_at, 'trashed preview booking must be soft-deleted');
        $this->assertSame($admin->id, $trashed->deleted_by);
    }

    public function test_preview_seeder_creates_expected_role_accounts(): void
    {
        $this->seed(DevRolePreviewSeeder::class);

        $accounts = [
            'admin@soleil.test' => UserRole::ADMIN,
            'moderator@soleil.test' => UserRole::MODERATOR,
            'user@soleil.test' => UserRole::USER,
            'pending@soleil.test' => UserRole::USER,
        ];

        foreach ($accounts as $email => $expectedRole) {
            $user = User::query()->where('email', $email)->first();
            $this->assertNotNull($user, "preview seeder must create {$email}");
            $this->assertSame($expectedRole, $user->role);
        }

        $this->assertNull(User::query()->where('email', 'pending@soleil.test')->firstOrFail()->email_verified_at);
        $this->assertNotNull(User::query()->where('email', 'admin@soleil.test')->firstOrFail()->email_verified_at);
    }
}
