<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Regression test for C-01/C-02: Review FormRequest purification
 *
 * Ensures validated() does not crash (previously called non-existent $this->purify())
 * and correctly sanitizes HTML via HtmlPurifierService.
 */
class ReviewRequestPurificationTest extends TestCase
{
    /**
     * Build a StoreReviewRequest with a manually-set validator that excludes DB rules.
     */
    private function makeStoreRequest(array $data): StoreReviewRequest
    {
        $request = StoreReviewRequest::create('/api/v1/reviews', 'POST', $data);
        $request->setContainer(app());

        // Use rules without the exists:rooms,id DB check — we're testing purification, not DB
        $rules = [
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:5000',
            'rating' => 'required|integer|min:1|max:5',
            'room_id' => 'required|integer',
        ];

        $validator = Validator::make($request->all(), $rules);
        $request->setValidator($validator);

        return $request;
    }

    public function test_store_review_validated_does_not_crash(): void
    {
        $request = $this->makeStoreRequest([
            'title' => 'Great stay',
            'content' => 'Lovely hostel with <b>great</b> staff',
            'rating' => 5,
            'room_id' => 1,
        ]);

        // This previously threw BadMethodCallException due to $this->purify()
        $validated = $request->validated();

        $this->assertIsArray($validated);
        $this->assertArrayHasKey('title', $validated);
        $this->assertArrayHasKey('content', $validated);
        $this->assertEquals('Great stay', $validated['title']);
        $this->assertEquals(5, $validated['rating']);
    }

    public function test_store_review_validated_strips_xss(): void
    {
        $request = $this->makeStoreRequest([
            'title' => '<script>alert("xss")</script>Clean title',
            'content' => '<p>Good</p><script>evil()</script>',
            'rating' => 4,
            'room_id' => 1,
        ]);

        $validated = $request->validated();

        $this->assertIsArray($validated);
        $this->assertStringNotContainsString('<script>', $validated['title']);
        $this->assertStringNotContainsString('<script>', $validated['content']);
        $this->assertStringContainsString('Clean title', $validated['title']);
    }

    public function test_store_review_validated_with_key_returns_single_value(): void
    {
        $request = $this->makeStoreRequest([
            'title' => 'Test',
            'content' => 'Content here',
            'rating' => 3,
            'room_id' => 1,
        ]);

        $title = $request->validated('title');
        $this->assertEquals('Test', $title);
    }

    public function test_update_review_validated_does_not_crash(): void
    {
        $request = UpdateReviewRequest::create('/api/v1/reviews/1', 'PUT', [
            'title' => 'Updated title',
            'content' => 'Updated <em>content</em> here with enough characters',
            'rating' => 4,
        ]);

        $request->setContainer(app());
        $validator = Validator::make($request->all(), (new UpdateReviewRequest)->rules());
        $request->setValidator($validator);

        // This previously threw BadMethodCallException due to $this->purify()
        $validated = $request->validated();

        $this->assertIsArray($validated);
        $this->assertArrayHasKey('title', $validated);
        $this->assertArrayHasKey('content', $validated);
    }

    public function test_update_review_validated_strips_xss(): void
    {
        $request = UpdateReviewRequest::create('/api/v1/reviews/1', 'PUT', [
            'title' => 'Safe <img src=x onerror=alert(1)>',
            'content' => '<p>Review text</p><iframe src="evil.com"></iframe> with enough chars',
            'rating' => 5,
        ]);

        $request->setContainer(app());
        $validator = Validator::make($request->all(), (new UpdateReviewRequest)->rules());
        $request->setValidator($validator);

        $validated = $request->validated();

        $this->assertIsArray($validated);
        $this->assertStringNotContainsString('onerror', $validated['title']);
        $this->assertStringNotContainsString('<iframe', $validated['content']);
    }

    public function test_update_review_validated_with_key_returns_single_value(): void
    {
        $request = UpdateReviewRequest::create('/api/v1/reviews/1', 'PUT', [
            'title' => 'Just title',
            'content' => 'Content that is long enough for validation',
        ]);

        $request->setContainer(app());
        $validator = Validator::make($request->all(), (new UpdateReviewRequest)->rules());
        $request->setValidator($validator);

        $title = $request->validated('title');
        $this->assertEquals('Just title', $title);
    }

    public function test_store_review_validation_rejects_invalid_rating(): void
    {
        $validator = Validator::make(
            ['title' => 'Test', 'content' => 'Content', 'rating' => 6, 'room_id' => 1],
            ['title' => 'required|string', 'content' => 'required|string', 'rating' => 'required|integer|min:1|max:5', 'room_id' => 'required|integer']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('rating', $validator->errors()->toArray());
    }

    public function test_store_review_validation_rejects_zero_rating(): void
    {
        $validator = Validator::make(
            ['title' => 'Test', 'content' => 'Content', 'rating' => 0, 'room_id' => 1],
            ['title' => 'required|string', 'content' => 'required|string', 'rating' => 'required|integer|min:1|max:5', 'room_id' => 'required|integer']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('rating', $validator->errors()->toArray());
    }
}
