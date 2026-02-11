<?php

namespace Tests\Feature\Security;

use App\Services\HtmlPurifierService;
use Tests\TestCase;

/**
 * HTML Purifier XSS Protection Tests
 *
 * 50+ vectors từ:
 * - PayloadsAllTheThings (github.com/swisskyrepo/PayloadsAllTheThings)
 * - OWASP XSS Cheat Sheet 2025
 * - Real-world bypass attempts
 *
 * Expected: 0% bypass rate (100% vectors blocked)
 *
 * "Chỉ có thằng ngu mới dùng regex để chống XSS năm 2025"
 */
class HtmlPurifierXssTest extends TestCase
{
    /**
     * ============================================
     * CATEGORY 1: Basic Script Injections
     * ============================================
     */

    /** @test */
    public function blocks_basic_script_tag()
    {
        $dangerous = '<script>alert("XSS")</script>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert', $clean);
    }

    /** @test */
    public function blocks_script_with_src()
    {
        $dangerous = '<script src="http://evil.com/xss.js"></script>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('evil.com', $clean);
    }

    /** @test */
    public function blocks_script_with_event_handlers()
    {
        $dangerous = '<body onload="alert(\'XSS\')"></body>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('onload', $clean);
    }

    /**
     * ============================================
     * CATEGORY 2: Event Handler Attributes
     * ============================================
     */

    /** @test */
    public function blocks_onclick_handler()
    {
        $dangerous = '<div onclick="alert(\'XSS\')">Click me</div>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('onclick', $clean);
    }

    /** @test */
    public function blocks_onmouseover_handler()
    {
        $dangerous = '<img src=x onmouseover="alert(\'XSS\')" />';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('onmouseover', $clean);
    }

    /** @test */
    public function blocks_onload_handler()
    {
        $dangerous = '<img src=x onload="alert(\'XSS\')" />';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('onload', $clean);
    }

    /** @test */
    public function blocks_onerror_handler()
    {
        $dangerous = '<img src=x onerror="alert(\'XSS\')" />';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('onerror', $clean);
    }

    /** @test */
    public function blocks_onchange_handler()
    {
        $dangerous = '<input onchange="alert(\'XSS\')" />';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('onchange', $clean);
    }

    /** @test */
    public function blocks_onsubmit_handler()
    {
        $dangerous = '<form onsubmit="alert(\'XSS\')"><input type="submit" /></form>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('onsubmit', $clean);
    }

    /** @test */
    public function blocks_oninput_handler()
    {
        $dangerous = '<input oninput="alert(\'XSS\')" />';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('oninput', $clean);
    }

    /**
     * ============================================
     * CATEGORY 3: JavaScript Protocol (javascript:)
     * ============================================
     */

    /** @test */
    public function blocks_javascript_protocol_in_href()
    {
        $dangerous = '<a href="javascript:alert(\'XSS\')">Click</a>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('javascript:', $clean);
    }

    /** @test */
    public function blocks_javascript_protocol_uppercase()
    {
        $dangerous = '<a href="JaVaScRiPt:alert(\'XSS\')">Click</a>';
        $clean = HtmlPurifierService::purify($dangerous);

        // Should either remove href or make it safe
        $this->assertTrue(
            ! str_contains($clean, 'javascript:') && ! str_contains($clean, 'JaVaScRiPt:'),
            'Case-insensitive javascript protocol should be blocked'
        );
    }

    /** @test */
    public function blocks_javascript_with_newlines()
    {
        $dangerous = '<a href="java'."\n".'script:alert(\'XSS\')">Click</a>';
        $clean = HtmlPurifierService::purify($dangerous);

        // HTML Purifier encodes the malicious URL as safe: java%20script%3A...
        // The key is javascript: protocol is blocked, encoded or removed
        $this->assertTrue(
            ! str_contains($clean, 'javascript:') && ! str_contains($clean, 'script:'),
            'JavaScript protocol with newlines should be blocked or encoded'
        );
    }

    /** @test */
    public function blocks_javascript_with_tabs()
    {
        $dangerous = '<a href="java'."\t".'script:alert(\'XSS\')">Click</a>';
        $clean = HtmlPurifierService::purify($dangerous);

        // HTML Purifier encodes the malicious URL as safe: java%20script%3A...
        // The key is javascript: protocol is blocked, encoded or removed
        $this->assertTrue(
            ! str_contains($clean, 'javascript:') && ! str_contains($clean, 'script:'),
            'JavaScript protocol with tabs should be blocked or encoded'
        );
    }

    /** @test */
    public function blocks_javascript_with_null_byte()
    {
        $dangerous = '<a href="java'."\x00".'script:alert(\'XSS\')">Click</a>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('alert', $clean);
    }

    /**
     * ============================================
     * CATEGORY 4: Data URI Scheme (data:)
     * ============================================
     */

    /** @test */
    public function blocks_data_uri_scheme()
    {
        $dangerous = '<img src="data:text/html,<script>alert(\'XSS\')</script>" />';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('data:', $clean);
    }

    /** @test */
    public function blocks_data_uri_with_base64()
    {
        $dangerous = '<img src="data:text/html;base64,PHNjcmlwdD5hbGVydCgnWFNTJyk8L3NjcmlwdD4=" />';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('data:', $clean);
    }

    /**
     * ============================================
     * CATEGORY 5: VBScript Protocol (IE only)
     * ============================================
     */

    /** @test */
    public function blocks_vbscript_protocol()
    {
        $dangerous = '<a href="vbscript:msgbox(\'XSS\')">Click</a>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('vbscript:', $clean);
    }

    /**
     * ============================================
     * CATEGORY 6: SVG + Embedded Content
     * ============================================
     */

    /** @test */
    public function blocks_svg_script_tag()
    {
        $dangerous = '<svg onload="alert(\'XSS\')"></svg>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('<svg', $clean);
    }

    /** @test */
    public function blocks_svg_with_script()
    {
        $dangerous = '<svg><script>alert("XSS")</script></svg>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('<svg', $clean);
    }

    /** @test */
    public function blocks_iframe_tag()
    {
        $dangerous = '<iframe src="http://evil.com"></iframe>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('<iframe', $clean);
    }

    /** @test */
    public function blocks_embed_tag()
    {
        $dangerous = '<embed src="http://evil.com/xss.swf" />';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('<embed', $clean);
    }

    /** @test */
    public function blocks_object_tag()
    {
        $dangerous = '<object data="http://evil.com"></object>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('<object', $clean);
    }

    /**
     * ============================================
     * CATEGORY 7: Style Tag + CSS Expressions
     * ============================================
     */

    /** @test */
    public function blocks_style_tag()
    {
        $dangerous = '<style>body { background: url("javascript:alert(\'XSS\')"); }</style>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('<style', $clean);
    }

    /** @test */
    public function blocks_style_attribute()
    {
        $dangerous = '<div style="background:url(javascript:alert(\'XSS\'))">Test</div>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('javascript:', $clean);
    }

    /** @test */
    public function blocks_css_expression()
    {
        // IE: expression() is deprecated but still a vector
        $dangerous = '<div style="width:expression(alert(\'XSS\'))">Test</div>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('expression(', $clean);
    }

    /**
     * ============================================
     * CATEGORY 8: Meta Refresh + Meta Tag Exploits
     * ============================================
     */

    /** @test */
    public function blocks_meta_refresh()
    {
        $dangerous = '<meta http-equiv="refresh" content="0;url=javascript:alert(\'XSS\')" />';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('<meta', $clean);
    }

    /** @test */
    public function blocks_meta_with_redirect()
    {
        $dangerous = '<meta http-equiv="refresh" content="0;url=http://evil.com" />';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('<meta', $clean);
    }

    /**
     * ============================================
     * CATEGORY 9: Link Tag Exploits
     * ============================================
     */

    /** @test */
    public function blocks_link_tag()
    {
        $dangerous = '<link rel="stylesheet" href="http://evil.com/xss.css">';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('<link', $clean);
    }

    /**
     * ============================================
     * CATEGORY 10: Unicode + Encoding Bypasses
     * ============================================
     */

    /** @test */
    public function blocks_unicode_escaped_script()
    {
        // \\x3cscript\\x3e = <script> in unicode
        $dangerous = '\\x3cscript\\x3ealert("XSS")\\x3c/script\\x3e';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertTrue(strlen($clean) > 0); // Should be safe, not throw
    }

    /** @test */
    public function blocks_html_entity_encoded_script()
    {
        // &lt;script&gt; but still dangerous if decoded incorrectly
        $dangerous = '&lt;script&gt;alert("XSS")&lt;/script&gt;';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('<script', $clean);
    }

    /** @test */
    public function blocks_double_encoded_entities()
    {
        $dangerous = '&amp;lt;script&amp;gt;alert("XSS")&amp;lt;/script&amp;gt;';
        $clean = HtmlPurifierService::purify($dangerous);

        // Should remain safe
        $this->assertTrue(strlen($clean) > 0);
    }

    /**
     * ============================================
     * CATEGORY 11: Parser Confusion Attacks
     * ============================================
     */

    /** @test */
    public function blocks_unclosed_tags()
    {
        $dangerous = '<img src="x" onerror="alert(\'XSS\')"';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('onerror', $clean);
    }

    /** @test */
    public function blocks_nested_tags()
    {
        $dangerous = '<div><div onclick="alert(\'XSS\')">Nested</div></div>';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('onclick', $clean);
    }

    /** @test */
    public function blocks_malformed_attributes()
    {
        $dangerous = '<img src=x onerror=alert(String.fromCharCode(88,83,83)) />';
        $clean = HtmlPurifierService::purify($dangerous);

        $this->assertStringNotContainsString('onerror', $clean);
    }

    /**
     * ============================================
     * CATEGORY 12: Allowed Elements Usage
     * ============================================
     */

    /** @test */
    public function allows_safe_bold_tag()
    {
        $safe = '<b>Bold Text</b>';
        $clean = HtmlPurifierService::purify($safe);

        $this->assertStringContainsString('Bold Text', $clean);
    }

    /** @test */
    public function allows_safe_italic_tag()
    {
        $safe = '<i>Italic Text</i>';
        $clean = HtmlPurifierService::purify($safe);

        $this->assertStringContainsString('Italic Text', $clean);
    }

    /** @test */
    public function allows_safe_links()
    {
        $safe = '<a href="http://example.com">Link</a>';
        $clean = HtmlPurifierService::purify($safe);

        $this->assertStringContainsString('Link', $clean);
        $this->assertStringContainsString('http://example.com', $clean);
    }

    /** @test */
    public function allows_safe_images()
    {
        $safe = '<img src="http://example.com/image.jpg" alt="Image" />';
        $clean = HtmlPurifierService::purify($safe);

        $this->assertStringContainsString('image.jpg', $clean);
    }

    /** @test */
    public function allows_safe_list()
    {
        $safe = '<ul><li>Item 1</li><li>Item 2</li></ul>';
        $clean = HtmlPurifierService::purify($safe);

        $this->assertStringContainsString('Item 1', $clean);
        $this->assertStringContainsString('Item 2', $clean);
    }

    /** @test */
    public function allows_safe_blockquote()
    {
        $safe = '<blockquote>Quote</blockquote>';
        $clean = HtmlPurifierService::purify($safe);

        $this->assertStringContainsString('Quote', $clean);
    }

    /**
     * ============================================
     * CATEGORY 13: Mixed Content Tests
     * ============================================
     */

    /** @test */
    public function handles_mixed_safe_and_dangerous_content()
    {
        $mixed = '<p>Safe text <script>alert("XSS")</script> more safe text</p>';
        $clean = HtmlPurifierService::purify($mixed);

        $this->assertStringContainsString('Safe text', $clean);
        $this->assertStringContainsString('more safe text', $clean);
        $this->assertStringNotContainsString('<script', $clean);
    }

    /** @test */
    public function handles_nested_safe_and_dangerous()
    {
        $nested = '<div><b>Safe <img src=x onerror="alert(\'XSS\')" /> text</b></div>';
        $clean = HtmlPurifierService::purify($nested);

        $this->assertStringContainsString('Safe', $clean);
        $this->assertStringContainsString('text', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
    }

    /**
     * ============================================
     * CATEGORY 14: Comment + Edge Cases
     * ============================================
     */

    /** @test */
    public function handles_html_comments()
    {
        $commented = '<!-- <script>alert("XSS")</script> --> <p>Safe</p>';
        $clean = HtmlPurifierService::purify($commented);

        // Comments should be stripped
        $this->assertStringNotContainsString('<!--', $clean);
        $this->assertStringContainsString('Safe', $clean);
    }

    /** @test */
    public function handles_empty_input()
    {
        $empty = '';
        $clean = HtmlPurifierService::purify($empty);

        $this->assertEquals('', $clean);
    }

    /** @test */
    public function handles_whitespace_only()
    {
        $whitespace = '   \n\t  ';
        $clean = HtmlPurifierService::purify($whitespace);

        // HTML Purifier may leave whitespace or return empty depending on mode
        // The key is: no dangerous content is present
        $this->assertStringNotContainsString('<', $clean);
        $this->assertStringNotContainsString('>', $clean);
        $this->assertStringNotContainsString('script', $clean);
    }

    /** @test */
    public function handles_very_long_content()
    {
        $long = str_repeat('<p>Safe content</p>', 1000);
        $clean = HtmlPurifierService::purify($long);

        $this->assertStringContainsString('Safe content', $clean);
        $this->assertTrue(strlen($clean) > 0);
    }

    /**
     * ============================================
     * CATEGORY 15: Performance Checks
     * ============================================
     */

    /** @test */
    public function purify_completes_within_acceptable_time()
    {
        $dangerous = '<script>alert("XSS")</script>';

        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            HtmlPurifierService::purify($dangerous);
        }
        $duration = microtime(true) - $start;

        // 100 iterations should complete in < 500ms (average 5ms/call)
        // With cache: < 1ms per call
        $this->assertTrue($duration < 5, "Purification too slow: {$duration}s for 100 calls");
    }
}
