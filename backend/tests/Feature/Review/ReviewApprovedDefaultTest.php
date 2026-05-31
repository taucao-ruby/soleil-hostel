<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SH-09 / F-44: reviews.approved must default to false so a row created without
 * an explicit `approved` value is NOT auto-approved (matching the Review model
 * default and safe-moderation intent).
 *
 * The DB-default assertion reads the column default straight from the catalog:
 * an Eloquent insert always applies the model's $attributes default first, so
 * the storage-layer default can only be isolated at the schema level. A
 * model-level round-trip test guards the application-facing behavior too.
 */
class ReviewApprovedDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_column_default_for_approved_is_false(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $default = DB::scalar(
                "SELECT column_default FROM information_schema.columns
                 WHERE table_name = 'reviews' AND column_name = 'approved'"
            );

            $this->assertNotNull($default, 'reviews.approved should have an explicit DB default.');
            $this->assertStringContainsString(
                'false',
                strtolower((string) $default),
                "reviews.approved DB default must be false; got: {$default}"
            );

            return;
        }

        if ($driver === 'sqlite') {
            $columns = DB::select("PRAGMA table_info('reviews')");
            $approved = collect($columns)->firstWhere('name', 'approved');

            $this->assertNotNull($approved, 'reviews.approved column must exist.');
            // SQLite stores boolean defaults as 0/1; false => 0.
            $this->assertSame('0', (string) $approved->dflt_value, 'reviews.approved SQLite default must be 0 (false).');

            return;
        }

        $this->markTestSkipped("Unsupported driver for column-default assertion: {$driver}");
    }

    public function test_model_create_without_approved_is_not_approved(): void
    {
        $booking = Booking::factory()->create();

        $review = Review::create([
            'room_id' => $booking->room_id,
            'user_id' => $booking->user_id,
            'booking_id' => $booking->id,
            'title' => 'Nice',
            'content' => 'Clean room',
            'guest_name' => 'Eloquent Guest',
            'rating' => 4,
        ]);

        $this->assertFalse($review->approved, 'A review created without approved must default to not-approved.');
        $this->assertFalse(Review::findOrFail($review->id)->approved);
    }
}
