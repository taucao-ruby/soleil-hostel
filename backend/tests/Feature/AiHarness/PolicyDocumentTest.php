<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\Models\PolicyDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_policy_documents_table(): void
    {
        $this->assertTrue(
            \Schema::hasTable('policy_documents'),
            'policy_documents table should exist after migration',
        );
    }

    public function test_seeder_creates_all_five_policies(): void
    {
        $this->seed(\Database\Seeders\PolicyDocumentSeeder::class);

        $this->assertDatabaseCount('policy_documents', 5);
        $this->assertDatabaseHas('policy_documents', ['slug' => 'cancellation-policy']);
        $this->assertDatabaseHas('policy_documents', ['slug' => 'checkin-checkout-policy']);
        $this->assertDatabaseHas('policy_documents', ['slug' => 'house-rules']);
        $this->assertDatabaseHas('policy_documents', ['slug' => 'amenities-list']);
        $this->assertDatabaseHas('policy_documents', ['slug' => 'payment-methods']);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(\Database\Seeders\PolicyDocumentSeeder::class);
        $this->seed(\Database\Seeders\PolicyDocumentSeeder::class);

        $this->assertDatabaseCount('policy_documents', 5);
    }

    public function test_seeded_docs_are_active_and_verified(): void
    {
        $this->seed(\Database\Seeders\PolicyDocumentSeeder::class);

        $docs = PolicyDocument::all();
        foreach ($docs as $doc) {
            $this->assertTrue($doc->is_active, "{$doc->slug} should be active");
            $this->assertNotNull($doc->last_verified_at, "{$doc->slug} should have last_verified_at");
        }
    }

    public function test_active_scope_filters_inactive(): void
    {
        $this->seed(\Database\Seeders\PolicyDocumentSeeder::class);
        PolicyDocument::where('slug', 'house-rules')->update(['is_active' => false]);

        $active = PolicyDocument::active()->get();
        $this->assertCount(4, $active);
        $this->assertFalse($active->pluck('slug')->contains('house-rules'));
    }

    public function test_by_slug_scope(): void
    {
        $this->seed(\Database\Seeders\PolicyDocumentSeeder::class);

        $doc = PolicyDocument::bySlug('cancellation-policy')->first();
        $this->assertNotNull($doc);
        $this->assertSame('cancellation-policy', $doc->slug);
    }

    public function test_by_category_scope(): void
    {
        $this->seed(\Database\Seeders\PolicyDocumentSeeder::class);

        $docs = PolicyDocument::byCategory('checkin')->get();
        $this->assertCount(1, $docs);
        $this->assertSame('checkin-checkout-policy', $docs->first()->slug);
    }

    public function test_is_stale_returns_false_for_recently_verified(): void
    {
        $this->seed(\Database\Seeders\PolicyDocumentSeeder::class);

        $doc = PolicyDocument::first();
        $this->assertFalse($doc->isStale());
    }

    public function test_is_stale_returns_true_for_old_verification(): void
    {
        $this->seed(\Database\Seeders\PolicyDocumentSeeder::class);

        $doc = PolicyDocument::first();
        $doc->update(['last_verified_at' => now()->subHours(25)]);
        $doc->refresh();

        $this->assertTrue($doc->isStale());
    }

    public function test_is_stale_returns_true_for_null_verification(): void
    {
        $this->seed(\Database\Seeders\PolicyDocumentSeeder::class);

        $doc = PolicyDocument::first();
        $doc->update(['last_verified_at' => null]);
        $doc->refresh();

        $this->assertTrue($doc->isStale());
    }
}
