<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * HTML Purifier Service - Singleton
 *
 * Sanitize HTML content từ user input một cách an toàn:
 * - Sử dụng whitelist thay vì blacklist (regex KHÔNG dùng)
 * - Cache config để performance <1ms
 * - Auto-detect dev vs prod environment
 *
 * Usage:
 * HtmlPurifierService::purify($htmlContent)
 * HtmlPurifierService::purify($content, ['allowed_elements' => ['b', 'i']])
 */
class HtmlPurifierService
{
    private static ?self $instance = null;

    private ?HTMLPurifier $purifier = null;

    private ?HTMLPurifier_Config $config = null;

    /**
     * Singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Purify HTML content
     *
     * @param  string  $html  Raw HTML input từ user
     * @param  array  $options  Override config (optional)
     * @return string Clean HTML safe để render
     */
    public static function purify(string $html, array $options = []): string
    {
        return self::getInstance()->doPurify($html, $options);
    }

    /**
     * Purify + return plain text (strip all HTML)
     *
     * @param  string  $html  Raw HTML input
     * @return string Plain text, safe để dùng
     */
    public static function plaintext(string $html): string
    {
        return strip_tags(self::purify($html));
    }

    /**
     * Check if string contains HTML (dùng trước purify để decide)
     */
    public static function isHtml(string $input): bool
    {
        return strlen($input) !== strlen(strip_tags($input));
    }

    /**
     * Actual purification logic
     */
    private function doPurify(string $html, array $options = []): string
    {
        if (empty($html)) {
            return '';
        }

        // Load config từ config/purifier.php
        try {
            $baseConfig = config('purifier.'.(app()->isLocal() ? 'dev' : 'prod'), []);
        } catch (\Throwable $e) {
            // Nếu app() chưa boot, use default dev config
            $baseConfig = config('purifier.dev', []);
        }

        // Override với options nếu có
        if (! empty($options)) {
            $baseConfig = array_merge($baseConfig, $options);
        }

        // Init HTMLPurifier_Config
        $config = HTMLPurifier_Config::createDefault();

        // Set whitelist elements
        if (isset($baseConfig['HTML.AllowedElements'])) {
            $config->set('HTML.AllowedElements', implode(',', $baseConfig['HTML.AllowedElements']));
        }

        // Set whitelist attributes
        if (isset($baseConfig['HTML.AllowedAttributes'])) {
            $allowedAttrs = [];
            foreach ($baseConfig['HTML.AllowedAttributes'] as $key => $value) {
                if ($value === true) {
                    $allowedAttrs[] = $key;
                }
            }
            $config->set('HTML.AllowedAttributes', implode(',', $allowedAttrs));
        }

        // Set allowed URI schemes (block javascript:, data:)
        if (isset($baseConfig['URI.AllowedSchemes'])) {
            $schemes = array_keys(array_filter($baseConfig['URI.AllowedSchemes']));
            $config->set('URI.AllowedSchemes', $schemes);
        }

        // CSS properties - strict disable
        if (isset($baseConfig['CSS.AllowedProperties'])) {
            // CSS.AllowedProperties phải là array, không phải boolean
            $cssProps = $baseConfig['CSS.AllowedProperties'];
            if ($cssProps === false) {
                $cssProps = []; // false => empty array để disable
            }
            $config->set('CSS.AllowedProperties', $cssProps);
        }

        // Other settings
        if (isset($baseConfig['HTML.TargetBlank'])) {
            $config->set('HTML.TargetBlank', $baseConfig['HTML.TargetBlank']);
        }
        if (isset($baseConfig['HTML.TidyLevel'])) {
            $config->set('HTML.TidyLevel', $baseConfig['HTML.TidyLevel']);
        }
        if (isset($baseConfig['Core.Language'])) {
            $config->set('Core.Language', $baseConfig['Core.Language']);
        }
        if (isset($baseConfig['Core.Encoding'])) {
            $config->set('Core.Encoding', $baseConfig['Core.Encoding']);
        }

        // Cache directory cho config
        try {
            $cacheDir = storage_path('framework/cache/purifier');
        } catch (\Throwable $e) {
            $cacheDir = sys_get_temp_dir().'/purifier';
        }

        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        try {
            $enableCache = config('purifier.enable_cache', false);
        } catch (\Throwable $e) {
            $enableCache = false;
        }

        if ($enableCache) {
            $config->set('Cache.SerializerPath', $cacheDir);
        } else {
            $config->set('Cache.DefinitionImpl', null);
        }

        // Khởi tạo HTMLPurifier
        $purifier = new HTMLPurifier($config);

        // Purify!
        $clean = $purifier->purify($html);

        // Log attempt nếu enabled
        try {
            if (config('purifier.log_attempts', true) && $html !== $clean) {
                \Log::warning('XSS content detected and purified', [
                    'original_length' => strlen($html),
                    'cleaned_length' => strlen($clean),
                ]);
            }
        } catch (\Throwable $e) {
            // Logging not available, skip
        }

        return $clean;
    }

    /**
     * Reset singleton cache (dùng trong tests)
     */
    public static function flush(): void
    {
        self::$instance = null;
    }
}
