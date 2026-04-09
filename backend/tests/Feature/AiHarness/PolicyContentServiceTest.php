<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\AiHarness\Services\PolicyContentService;
use App\Models\PolicyDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyContentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PolicyContentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PolicyDocumentSeeder::class);
        $this->service = app(PolicyContentService::class);
    }

    public function test_find_by_query_returns_matching_documents(): void
    {
        $results = $this->service->findByQuery('hủy đặt phòng');

        $this->assertNotEmpty($results);
        $this->assertTrue($results->pluck('slug')->contains('cancellation-policy'));
    }

    public function test_find_by_query_returns_empty_for_no_match(): void
    {
        $results = $this->service->findByQuery('xyznonexistent');

        $this->assertTrue($results->isEmpty());
    }

    public function test_find_by_query_returns_empty_for_blank_input(): void
    {
        $results = $this->service->findByQuery('');
        $this->assertTrue($results->isEmpty());

        $results = $this->service->findByQuery('   ');
        $this->assertTrue($results->isEmpty());
    }

    public function test_find_by_query_excludes_inactive_documents(): void
    {
        PolicyDocument::where('slug', 'cancellation-policy')->update(['is_active' => false]);

        $results = $this->service->findByQuery('hủy');
        $this->assertFalse($results->pluck('slug')->contains('cancellation-policy'));
    }

    public function test_find_by_query_excludes_stale_documents(): void
    {
        PolicyDocument::where('slug', 'cancellation-policy')
            ->update(['last_verified_at' => now()->subHours(25)]);

        $results = $this->service->findByQuery('hủy');
        $this->assertFalse($results->pluck('slug')->contains('cancellation-policy'));
    }

    public function test_get_by_slug_returns_document(): void
    {
        $doc = $this->service->getBySlug('house-rules');

        $this->assertNotNull($doc);
        $this->assertSame('house-rules', $doc->slug);
        $this->assertSame('rules', $doc->category);
    }

    public function test_get_by_slug_returns_null_for_missing(): void
    {
        $doc = $this->service->getBySlug('nonexistent');
        $this->assertNull($doc);
    }

    public function test_get_by_slug_returns_null_for_inactive(): void
    {
        PolicyDocument::where('slug', 'house-rules')->update(['is_active' => false]);

        $doc = $this->service->getBySlug('house-rules');
        $this->assertNull($doc);
    }

    public function test_get_support_contact_returns_string(): void
    {
        $contact = $this->service->getSupportContact();
        $this->assertNotEmpty($contact);
        $this->assertIsString($contact);
    }

    public function test_get_policy_url_returns_valid_url(): void
    {
        $url = $this->service->getPolicyUrl('cancellation-policy');
        $this->assertStringContainsString('cancellation-policy', $url);
        $this->assertStringContainsString('/policies/', $url);
    }
}
