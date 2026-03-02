<?php

namespace Tests\Unit\Models;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for the Review model (H-05).
 *
 * Note: ReviewController does not exist yet (routes commented out in api.php).
 * These tests cover model behavior, scopes, and purification.
 */
class ReviewModelTest extends TestCase
{
    use RefreshDatabase;

    private function createReviewWithDeps(array $overrides = []): Review
    {
        $user = User::factory()->create();
        $location = Location::factory()->create();
        $room = Room::factory()->create(['location_id' => $location->id]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
        ]);

        return Review::create(array_merge([
            'title' => 'Great stay',
            'content' => 'Very clean and comfortable.',
            'rating' => 5,
            'room_id' => $room->id,
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'guest_name' => 'Nguyen Van A',
            'guest_email' => 'guest@example.com',
        ], $overrides));
    }

    public function test_review_can_be_created(): void
    {
        $review = $this->createReviewWithDeps();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
        $this->assertEquals(5, $review->rating);
    }

    public function test_review_defaults_to_unapproved(): void
    {
        $review = $this->createReviewWithDeps();

        $this->assertFalse($review->approved);
    }

    public function test_approved_scope_filters_correctly(): void
    {
        $this->createReviewWithDeps(['approved' => true]);
        $this->createReviewWithDeps(['approved' => false]);

        $approvedCount = Review::approved()->count();
        $this->assertEquals(1, $approvedCount);
    }

    public function test_high_rated_scope_filters_correctly(): void
    {
        $this->createReviewWithDeps(['rating' => 5]);
        $this->createReviewWithDeps(['rating' => 3]);
        $this->createReviewWithDeps(['rating' => 4]);

        $highRatedCount = Review::highRated()->count();
        $this->assertEquals(2, $highRatedCount);
    }

    public function test_review_belongs_to_room(): void
    {
        $review = $this->createReviewWithDeps();

        $this->assertNotNull($review->room);
        $this->assertInstanceOf(Room::class, $review->room);
    }

    public function test_review_belongs_to_user(): void
    {
        $review = $this->createReviewWithDeps();

        $this->assertNotNull($review->user);
        $this->assertInstanceOf(User::class, $review->user);
    }

    public function test_review_belongs_to_booking(): void
    {
        $review = $this->createReviewWithDeps();

        $this->assertNotNull($review->booking);
        $this->assertInstanceOf(Booking::class, $review->booking);
    }

    public function test_purifiable_fields_defined(): void
    {
        $review = new Review;

        $fields = $review->getPurifiableFields();
        $this->assertContains('title', $fields);
        $this->assertContains('content', $fields);
        $this->assertContains('guest_name', $fields);
    }

    public function test_review_purifies_xss_on_save(): void
    {
        $review = $this->createReviewWithDeps([
            'title' => '<script>alert("xss")</script>Clean Title',
            'content' => '<img onerror=alert(1) src=x>Safe content',
            'guest_name' => '<b onmouseover=alert(1)>Guest</b>',
        ]);

        $review->refresh();

        $this->assertStringNotContainsString('<script>', $review->title);
        $this->assertStringNotContainsString('onerror', $review->content);
    }

    public function test_recent_scope_orders_by_created_at_desc(): void
    {
        $old = $this->createReviewWithDeps();
        $old->created_at = now()->subDays(5);
        $old->save();

        $new = $this->createReviewWithDeps();

        $reviews = Review::recent()->get();
        $this->assertEquals($new->id, $reviews->first()->id);
    }
}
