<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait HasCacheTagSupport
{
    /**
     * Check if the cache driver supports tagging.
     */
    protected function supportsTags(): bool
    {
        return Cache::supportsTags();
    }
}
