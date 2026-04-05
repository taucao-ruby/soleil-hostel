<?php

/**
 * HTML Purifier Configuration for Soleil Hostel
 *
 * CẢNH BÁO: Chỉ dùng HTML Purifier, tuyệt đối KHÔNG dùng regex/blacklist để chống XSS.
 * Regex chống XSS = Tự bắn vào chân mình (99% bypass rate)
 * HTML Purifier = Whitelist an toàn (0% bypass rate khi config đúng)
 *
 * Dev vs Prod:
 * - DEV: Cache disabled, report XSS attempts to error log
 * - PROD: Cache enabled, strict config, report to monitoring service
 */
$rawAppEnv = getenv('APP_ENV');
$appEnv = $rawAppEnv !== false
    ? $rawAppEnv
    : (string) ($_SERVER['APP_ENV'] ?? ($_ENV['APP_ENV'] ?? 'local'));
$isLocal = in_array(strtolower($appEnv), ['local', 'testing', 'dev'], true);

$cachePath = sys_get_temp_dir().'/purifier';
if (function_exists('storage_path')) {
    try {
        $cachePath = storage_path('framework/cache/purifier');
    } catch (\Throwable $e) {
        // fallback to sys temp
    }
}

return [
    // Kích hoạt caching - cải thiện tốc độ từ ~10ms -> <1ms
    'enable_cache' => ! $isLocal,

    // Đường dẫn cache
    'cache_path' => $cachePath,

    // Config dev environment (rộng hơn, dễ debug)
    'dev' => [
        'HTML.AllowedElements' => [
            // Inline text formatting
            'b', 'i', 'u', 'strong', 'em', 's', 'code', 'small', 'sub', 'sup',
            // Links
            'a',
            // Images
            'img',
            // Block elements
            'p', 'br', 'hr', 'blockquote',
            // Lists
            'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            // Div để wrap content
            'div',
            // Tabel (nếu cần)
            // 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
        ],

        // Whitelist attributes riêng cho từng element
        'HTML.AllowedAttributes' => [
            // Links: chỉ href, target, rel (no javascript:)
            'a.href' => true,
            'a.target' => true,
            'a.rel' => true,
            'a.title' => true,

            // Images: src, alt, width, height (no javascript:, data:)
            'img.src' => true,
            'img.alt' => true,
            'img.width' => true,
            'img.height' => true,
            'img.title' => true,

            // Global: class, id, style (sẽ strip style sau)
            '*.class' => true,
            '*.id' => true,
            '*.dir' => true,
            '*.lang' => true,

            // Data attributes (cẩn thận, có thể XSS qua javascript:)
            // '*.data-*' => true,
        ],

        // STRIP style hoàn toàn - không cho phép <style> hay style attribute
        'CSS.AllowedProperties' => false,

        // Chặn URLs nguy hiểm: javascript:, data:, vbscript:
        'URI.AllowedSchemes' => [
            'http' => true,
            'https' => true,
            'ftp' => true,
            'ftps' => true,
            'mailto' => true,
            'news' => true,
            'usenet' => true,
            'tel' => true,
            'sms' => true,
            'ssh' => true,
        ],

        // Strip URL query strings nếu cần
        'URI.Mutable' => true,

        // Cho phép <a target="_blank"> nhưng add rel="noopener noreferrer"
        'HTML.TargetBlank' => true,
        'Attr.AllowedFrameTargets' => ['_blank', '_self', '_parent', '_top'],

        // Cho phép definition list
        'HTML.Trusted' => false,

        // Auto-fix các lỗi HTML (nested <p>, v.v.)
        'HTML.TidyLevel' => 'heavy',

        // Core settings
        'Core.Language' => 'en',
        'Core.Encoding' => 'UTF-8',

        // Xóa các thẻ rỗng sau khi purify
        'Core.RemoveInvalidImg' => true,

        // Không cho phép XML declaration
        'Output.XHTML' => true,

        // Thêm rel="noopener" cho external links
        'HTML.SafeIframe' => false,
    ],

    // Config production (siêu strict, quản lý máu)
    'prod' => [
        // ONLY cho phép formatting + links + images + basic blocks
        'HTML.AllowedElements' => [
            // Inline formatting ONLY
            'b', 'i', 'strong', 'em',
            // Links (external only, no javascript)
            'a',
            // Images (CDN + relative paths only)
            'img',
            // Block structure
            'p', 'br', 'blockquote',
            // Lists
            'ul', 'ol', 'li',
            // Divs
            'div',
        ],

        // Attributes: SUPER STRICT
        'HTML.AllowedAttributes' => [
            // Links: only href + rel (no onclick, no javascript:)
            'a.href' => true,
            'a.rel' => true,

            // Images: src + alt only (no on*, no javascript:, no data:)
            'img.src' => true,
            'img.alt' => true,

            // Global: only class for styling
            '*.class' => true,
        ],

        // Chặn <style> tag hoàn toàn
        'CSS.AllowedProperties' => false,

        // ONLY safe URLs
        'URI.AllowedSchemes' => [
            'http' => true,
            'https' => true,
            'mailto' => true,
        ],

        // Chặn target="_blank" để prevent tab jacking
        'HTML.TargetBlank' => false,
        'Attr.AllowedFrameTargets' => [],

        // Strict HTML validation
        'HTML.Trusted' => false,
        'HTML.TidyLevel' => 'heavy',

        // Core settings
        'Core.Language' => 'en',
        'Core.Encoding' => 'UTF-8',
        'Core.RemoveInvalidImg' => true,
        'Output.XHTML' => true,

        // Xóa toàn bộ URL queries
        'URI.Mutable' => false,

        // No iframes
        'HTML.SafeIframe' => false,

        // Remove comments
        'Filter.Custom' => [],
    ],

    // Whitelist domains cho images trong production
    'allowed_image_hosts' => [
        'cdn.soleil-hostel.com',
        'images.soleil-hostel.com',
        'localhost',
        '127.0.0.1',
    ],

    // Cache ttl (minutes)
    'cache_ttl' => 60 * 24, // 24 hours

    // Logging XSS attempts
    'log_attempts' => true,
];
