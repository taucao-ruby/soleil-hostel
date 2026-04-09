<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PolicyDocument extends Model
{
    use HasUuids;

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
