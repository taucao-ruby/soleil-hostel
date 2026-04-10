<?php

declare(strict_types=1);

namespace App\AiHarness\Services;

use App\Models\PolicyDocument;
use Illuminate\Support\Collection;

/**
 * READ_ONLY policy content service for AI harness.
 *
 * Source backing for 'get_faq_content' and 'lookup_policy' tools.
 * Never writes. Returns only active, non-stale documents.
 */
class PolicyContentService
{
    /**
     * Find policy documents matching a keyword query.
     *
     * Simple keyword match against title + content.
     * Returns only active, non-stale documents.
     * Returns empty collection if none found (never throws).
     */
    public function findByQuery(string $query, string $language = 'vi'): Collection
    {
        if (trim($query) === '') {
            return collect();
        }

        $keywords = array_filter(
            explode(' ', mb_strtolower(trim($query))),
            fn (string $w) => mb_strlen($w) >= 2,
        );

        if (empty($keywords)) {
            return collect();
        }

        $results = PolicyDocument::active()
            ->byLanguage($language)
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $q->where(function ($inner) use ($keyword) {
                        $inner->whereRaw('LOWER(title) LIKE ?', ["%{$keyword}%"])
                            ->orWhereRaw('LOWER(content) LIKE ?', ["%{$keyword}%"]);
                    });
                }
            })
            ->get();

        return $results->filter(fn (PolicyDocument $doc) => ! $doc->isStale());
    }

    /**
     * Get a single policy document by its slug.
     */
    public function getBySlug(string $slug): ?PolicyDocument
    {
        return PolicyDocument::active()
            ->bySlug($slug)
            ->first();
    }

    /**
     * Get the support contact string.
     */
    public function getSupportContact(): string
    {
        return config('app.support_contact', 'support@soleilhostel.vn | Hotline: 0909-123-456');
    }

    /**
     * Get the canonical URL for a policy document.
     */
    public function getPolicyUrl(string $slug): string
    {
        $baseUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        return "{$baseUrl}/policies/{$slug}";
    }
}
