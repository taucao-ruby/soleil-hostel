<?php

namespace App\Directives;

use App\Services\HtmlPurifierService;

/**
 * Blade Directive @purify
 *
 * Render HTML content an toàn trong views
 *
 * Usage:
 *
 * @purify($variable)
 * @purify($review->content)
 * {!! $review->content|purify !!}  // Filter syntax (nếu register làm filter)
 *
 * Nội bộ gọi HtmlPurifierService::purify()
 */
class PurifyDirective
{
    /**
     * Register Blade directive
     *
     * Called in AppServiceProvider::boot()
     */
    public static function register(): void
    {
        // @purify($content) directive
        \Blade::directive('purify', function ($expression) {
            return "<?php echo \App\Services\HtmlPurifierService::purify({$expression}); ?>";
        });

        // @purifyPlain($content) - strip all HTML, return plain text
        \Blade::directive('purifyPlain', function ($expression) {
            return "<?php echo \App\Services\HtmlPurifierService::plaintext({$expression}); ?>";
        });
    }
}
