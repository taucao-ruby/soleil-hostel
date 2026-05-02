<?php

declare(strict_types=1);

namespace App\Models;

use App\AiHarness\Services\PromptSanitizerService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PolicyDocument extends Model
{
    use HasUuids;

    /**
     * Maximum fraction of the raw content that may change under sanitization
     * before we reject the document at authoring time. A clean Markdown
     * policy diffs at ~0%; an injection attempt diffs sharply above this.
     */
    public const SANITIZATION_DIFF_THRESHOLD = 0.05;

    protected $fillable = [
        'slug',
        'title',
        'content',
        'category',
        'language',
        'is_active',
        'last_verified_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $doc): void {
            $doc->validateContent();
        });
    }

    /**
     * Reject content that diverges from its sanitized form by more than
     * SANITIZATION_DIFF_THRESHOLD. This catches authoring-time prompt
     * injection (control tokens, bidi marks, smuggled HTML) before the
     * document ever reaches the model context.
     *
     * @throws DomainException
     */
    public function validateContent(): void
    {
        $raw = (string) ($this->content ?? '');

        if ($raw === '') {
            return;
        }

        $sanitizer = app(PromptSanitizerService::class);
        $sanitized = $sanitizer->sanitizePolicyContent($raw);
        $diff = $sanitizer->diffRatio($raw, $sanitized);

        if ($diff > self::SANITIZATION_DIFF_THRESHOLD) {
            throw new DomainException(sprintf(
                'PolicyDocument content rejected: %.2f%% of content was modified by sanitization (threshold %.0f%%). This may indicate prompt injection in the document.',
                $diff * 100,
                self::SANITIZATION_DIFF_THRESHOLD * 100,
            ));
        }
    }

    // ───── Scopes ─────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    // ───── Accessors ─────

    public function isStale(): bool
    {
        if ($this->last_verified_at === null) {
            return true;
        }

        return $this->last_verified_at->diffInHours(now()) > 24;
    }
}
