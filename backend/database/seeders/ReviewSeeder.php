<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * ReviewSeeder - Test data for HTML Purifier validation
 *
 * This seeder creates reviews with:
 * 1. Safe HTML content (b, i, strong, em, a, lists)
 * 2. XSS attempts that should be stripped
 * 3. Edge cases and encoding bypasses
 * 4. Mixed valid/invalid content
 *
 * After seeding, all content is auto-purified by Review::Purifiable trait
 * Check storage/logs/laravel.log for "XSS content detected" warnings
 */
class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds
     */
    public function run(): void
    {
        $rooms = Room::all();
        $users = User::all();

        if ($rooms->isEmpty() || $users->isEmpty()) {
            $this->command->warn('⚠️  Rooms or Users not found. Run RoomSeeder and create users first.');

            return;
        }

        $reviews = [
            // ==============================================================
            // CATEGORY 1: Safe HTML Content (Should be preserved)
            // ==============================================================
            [
                'title' => '<b>Excellent Room!</b>',
                'content' => '<p>The room was <strong>amazing</strong>. The staff was <em>very friendly</em>. I would <i>definitely</i> recommend this place.</p>',
                'rating' => 5,
                'is_approved' => true,
                'description' => 'Safe: Basic formatting tags',
            ],
            [
                'title' => 'Great Value for Money',
                'content' => '<p>Check out their website: <a href="https://example.com">Click here</a></p><ul><li>Clean rooms</li><li>Good breakfast</li><li>Friendly staff</li></ul>',
                'rating' => 4,
                'is_approved' => true,
                'description' => 'Safe: Links and lists',
            ],
            [
                'title' => 'Perfect for Backpackers',
                'content' => '<blockquote><p>Best hostel I\'ve stayed at!</p></blockquote><p>Stayed here for <b>3 nights</b> and had a blast.</p>',
                'rating' => 5,
                'is_approved' => true,
                'description' => 'Safe: Blockquotes and emphasis',
            ],

            // ==============================================================
            // CATEGORY 2: Script Tags (Should be stripped)
            // ==============================================================
            [
                'title' => 'Good Room<script>alert("xss")</script>',
                'content' => '<p>Script tag in title is dangerous</p><script>fetch("https://evil.com")</script>',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Direct script tag injection',
            ],
            [
                'title' => 'Nice Place',
                'content' => '<p>Multiple scripts:</p><script>alert(1)</script><script src="https://evil.com/payload.js"></script>',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Multiple scripts and src attribute',
            ],

            // ==============================================================
            // CATEGORY 3: Event Handlers (Should be stripped)
            // ==============================================================
            [
                'title' => 'Amazing',
                'content' => '<p onmouseover="alert(\'xss\')" onclick="fetch(\'https://evil.com\')">Click me!</p>',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Event handler injection',
            ],
            [
                'title' => 'Great Experience',
                'content' => '<img src="x" onerror="alert(\'xss\')" />',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Image onerror handler',
            ],
            [
                'title' => 'Very Good',
                'content' => '<body onload="alert(\'xss\')">Room was nice</body>',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Body onload handler',
            ],

            // ==============================================================
            // CATEGORY 4: Protocol Handlers (Should be stripped)
            // ==============================================================
            [
                'title' => 'Best Hostel',
                'content' => '<a href="javascript:alert(\'xss\')">Click here</a>',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: JavaScript protocol in href',
            ],
            [
                'title' => 'Wonderful Stay',
                'content' => '<img src="data:text/html,<script>alert(\'xss\')</script>" />',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Data URI with embedded script',
            ],
            [
                'title' => 'Outstanding',
                'content' => '<a href="vbscript:msgbox(\'xss\')">Click</a>',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: VBScript protocol',
            ],

            // ==============================================================
            // CATEGORY 5: SVG/Style Injection (Should be stripped)
            // ==============================================================
            [
                'title' => 'Perfect',
                'content' => '<svg onload="alert(\'xss\')"><circle cx="50" cy="50" r="40"/></svg>',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: SVG with onload handler',
            ],
            [
                'title' => 'Excellent',
                'content' => '<style>body { background: url("javascript:alert(\'xss\')") }</style>',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: CSS with JavaScript protocol',
            ],
            [
                'title' => 'Amazing',
                'content' => '<p style="background: url(\'javascript:alert(1)\')">Text</p>',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Inline style with JavaScript',
            ],

            // ==============================================================
            // CATEGORY 6: Encoding Bypasses (Should be stripped)
            // ==============================================================
            [
                'title' => 'Great',
                'content' => '<img src="x" onerror="&#97;&#108;&#101;&#114;&#116;&#40;&#39;&#120;&#115;&#115;&#39;&#41;" />',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: HTML entity encoding of alert',
            ],
            [
                'title' => 'Good',
                'content' => '<img src="x" onerror="&#x61;&#x6c;&#x65;&#x72;&#x74;&#x28;&#x27;&#x78;&#x73;&#x73;&#x27;&#x29;" />',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Hex entity encoding of alert',
            ],
            [
                'title' => 'Nice',
                'content' => '<img src="x" onerror="eval(atob(\'YWxlcnQoJ3hzcycpOw==\'))" />',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Base64 encoded payload',
            ],

            // ==============================================================
            // CATEGORY 7: Mixed Valid/Invalid Content (Mixed result)
            // ==============================================================
            [
                'title' => 'Good but has injection',
                'content' => '<p>This is <b>safe content</b> mixed with <script>alert("xss")</script> malicious content.</p><p>More safe text</p>',
                'rating' => 4,
                'is_approved' => false,
                'description' => 'Mixed: Safe formatting + script injection',
            ],
            [
                'title' => 'Safe links but bad attrs',
                'content' => '<a href="https://example.com" onclick="alert(\'xss\')">Safe link text</a> with <i>formatting</i>',
                'rating' => 4,
                'is_approved' => false,
                'description' => 'Mixed: Safe href but dangerous onclick',
            ],

            // ==============================================================
            // CATEGORY 8: Parser Confusion (Should be stripped)
            // ==============================================================
            [
                'title' => 'Confused Parser Attempt',
                'content' => '<img src="x" alt="test" title="><script>alert(\'xss\')</script>',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Parser confusion with attribute injection',
            ],
            [
                'title' => 'Malformed Tag',
                'content' => '<p>Test<br<br<br<br<br<br<img src="x" onerror="alert(\'xss\')">',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Malformed tags with img onerror',
            ],

            // ==============================================================
            // CATEGORY 9: Null Bytes & Control Characters
            // ==============================================================
            [
                'title' => "Nice\0Place",
                'content' => "<p>Review with null byte\x00 embedded</p>",
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Null byte injection',
            ],

            // ==============================================================
            // CATEGORY 10: Edge Cases & Complex Scenarios
            // ==============================================================
            [
                'title' => 'Very Long Title With Mixed Content <b>Safe</b> <script>Bad</script>',
                'content' => str_repeat('<p>This is a long review with repeated content. ', 10).'<script>alert("xss")</script>',
                'rating' => 4,
                'is_approved' => false,
                'description' => 'Edge Case: Long content with late-stage script injection',
            ],
            [
                'title' => 'Unicode Test 中文 العربية 🔥',
                'content' => '<p>Review in multiple languages: <b>中文</b> <i>العربية</i> <strong>Русский</strong> with emoji 🎉</p>',
                'rating' => 5,
                'is_approved' => true,
                'description' => 'Safe: Unicode and emoji content',
            ],
            [
                'title' => 'Iframe Injection <iframe src="https://evil.com"></iframe>',
                'content' => '<p>Try to inject iframe</p><iframe src="https://evil.com/payload"></iframe>',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Iframe injection',
            ],
            [
                'title' => 'Embed Attempt',
                'content' => '<embed src="https://evil.com/flash.swf" /><object data="https://evil.com"></object>',
                'rating' => 3,
                'is_approved' => false,
                'description' => 'XSS Attempt: Embed and object tags',
            ],

            // ==============================================================
            // CATEGORY 11: Real-World Reviews (Safe Examples)
            // ==============================================================
            [
                'title' => '<b>Loved It!</b> Best Hostel in the City',
                'content' => '<p>Stayed here for <strong>5 nights</strong> and had an <em>amazing</em> experience.</p><p>Highlights:</p><ul><li>Clean and modern rooms</li><li>Friendly and helpful staff</li><li>Great common areas</li><li>Excellent breakfast</li></ul><p>Would <b>definitely</b> recommend to anyone visiting the city. Already planning my next trip!</p>',
                'rating' => 5,
                'is_approved' => true,
                'description' => 'Real: Detailed positive review with formatting',
            ],
            [
                'title' => 'Good Value Hostel',
                'content' => '<p>The price-to-quality ratio is <strong>excellent</strong>. For more info, check their <a href="https://example.com">website</a>.</p><p>Pros:</p><ul><li>Affordable</li><li>Well-located</li><li>Safe</li></ul><p>Cons:</p><ul><li>No private rooms</li><li>Limited kitchen access</li></ul>',
                'rating' => 4,
                'is_approved' => true,
                'description' => 'Real: Balanced review with pros/cons',
            ],
        ];

        foreach ($reviews as $reviewData) {
            $description = $reviewData['description'];
            unset($reviewData['description']);

            $room = $rooms->random();
            $user = $users->random();

            Review::create([
                ...$reviewData,
                'room_id' => $room->id,
                'user_id' => $user->id,
                'guest_name' => $user->name,
            ]);

            $this->command->line("✅ Created review: {$description}");
        }

        $this->command->info("\n✨ All reviews created and purified! Check storage/logs/laravel.log for XSS detection warnings.");
    }
}
