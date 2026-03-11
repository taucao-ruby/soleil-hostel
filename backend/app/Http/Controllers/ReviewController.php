<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Booking;
use App\Models\Review;
use App\Services\AdminAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ReviewController — CRUD for guest reviews.
 *
 * Authorization is enforced via ReviewPolicy:
 * - create: owner of a confirmed+past booking, no existing review, admin excluded
 * - update: owner only
 * - delete: owner OR admin (via policy before() bypass)
 */
class ReviewController extends Controller
{
    public function __construct(
        private AdminAuditService $auditService
    ) {}

    /**
     * POST /api/v1/reviews
     *
     * Create a review for a booking the authenticated user owns.
     * booking_id from the request body is used to load the Booking
     * (with 'review' relation pre-loaded for the uniqueness policy check).
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $booking = Booking::with('review')->findOrFail($validated['booking_id']);

        $this->authorize('create', [Review::class, $booking]);

        /** @var \App\Models\User $authUser */
        $authUser = auth()->user();

        $review = Review::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'rating' => $validated['rating'],
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'user_id' => $authUser->id,
            'guest_name' => $authUser->name,
            'guest_email' => $authUser->email,
            'approved' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đánh giá đã được tạo thành công.',
            'data' => $review,
        ], 201);
    }

    /**
     * PUT /api/v1/reviews/{review}
     *
     * Update an existing review. UpdateReviewRequest::authorize() and
     * ReviewPolicy::update() both enforce owner-only access.
     */
    public function update(UpdateReviewRequest $request, Review $review): JsonResponse
    {
        $this->authorize('update', $review);

        $review->update(array_filter(
            $request->validated(),
            fn ($v) => $v !== null
        ));

        return response()->json([
            'success' => true,
            'message' => 'Đánh giá đã được cập nhật thành công.',
            'data' => $review->fresh(),
        ]);
    }

    /**
     * DELETE /api/v1/reviews/{review}
     *
     * Delete a review. Owner or admin (policy before() bypass).
     */
    public function destroy(Request $request, Review $review): JsonResponse
    {
        $this->authorize('delete', $review);

        $isAdminDelete = auth()->user()->isAdmin() && $review->user_id !== auth()->id();

        $reviewId = $review->id;
        $review->delete();

        if ($isAdminDelete) {
            $this->auditService->log('review.admin_delete', 'review', $reviewId, [
                'review_owner_id' => $review->user_id,
                'booking_id' => $review->booking_id,
                'reason' => $request->input('reason'),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đánh giá đã được xóa thành công.',
        ]);
    }
}
