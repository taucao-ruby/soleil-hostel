<?php

declare(strict_types=1);

use App\Models\Room;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

/**
 * Concurrent booking stress test (50 simultaneous requests).
 *
 * Usage:
 * php tests/stubs/concurrent_booking_test.php
 *
 * This script bootstraps Laravel, creates one authenticated user per request
 * (so route throttling does not dominate results), then sends concurrent
 * booking requests for the same room and date range.
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apiUrl = rtrim((string) (getenv('STRESS_API_URL') ?: 'http://127.0.0.1:8000/api'), '/');
$totalRequests = (int) (getenv('STRESS_TOTAL_REQUESTS') ?: 50);

// Always create a fresh room to avoid conflicts with seeded/existing bookings
$room = Room::factory()->available()->create();

$authTokens = [];
for ($i = 0; $i < $totalRequests; $i++) {
    $user = User::factory()->create([
        'email' => 'stress-user-'.uniqid('', true)."-{$i}@example.com",
    ]);
    $authTokens[$i] = $user->createToken("stress-test-{$i}")->plainTextToken;
}

$roomId = (int) $room->id;
$successCount = 0;
$failureCount = 0;
$results = [];

echo "Starting concurrent booking stress test...\n";
echo "Total concurrent requests: {$totalRequests}\n";
echo "Room ID: {$roomId}\n\n";

$checkIn = (new DateTimeImmutable)->modify('+5 days')->format('Y-m-d');
$checkOut = (new DateTimeImmutable)->modify('+7 days')->format('Y-m-d');

echo "Date range: {$checkIn} to {$checkOut}\n";
echo "Sending requests...\n\n";

$mh = curl_multi_init();
$handles = [];

for ($i = 0; $i < $totalRequests; $i++) {
    $ch = curl_init();

    $payload = json_encode([
        'room_id' => $roomId,
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'guest_name' => "Stress Test User {$i}",
        'guest_email' => 'stress-booking-'.uniqid('', true)."-{$i}@example.com",
    ], JSON_THROW_ON_ERROR);

    curl_setopt_array($ch, [
        CURLOPT_URL => "{$apiUrl}/bookings",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer '.$authTokens[$i],
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
    ]);

    curl_multi_add_handle($mh, $ch);
    $handles[$i] = $ch;
}

$running = null;
do {
    curl_multi_exec($mh, $running);
    usleep(100000);
} while ($running > 0);

for ($i = 0; $i < $totalRequests; $i++) {
    $ch = $handles[$i];
    $response = curl_multi_getcontent($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $results[] = [
        'request' => $i + 1,
        'status' => $httpCode,
        'response' => $response,
    ];

    if ($httpCode === 201) {
        $successCount++;
        echo 'Request '.($i + 1).": SUCCESS (HTTP 201)\n";
    } else {
        $failureCount++;
        $statusLabel = match ($httpCode) {
            409 => 'Conflict (double-booking prevented)',
            422 => 'Unprocessable Entity (validation/conflict)',
            429 => 'Too Many Requests (rate limited)',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            500 => 'Server Error',
            default => 'Unknown',
        };
        // Show first non-429 error body for debugging
        if ($httpCode !== 429 && $failureCount <= 3) {
            $body = json_decode((string) $response, true);
            $msg = $body['message'] ?? substr((string) $response, 0, 120);
            echo 'Request '.($i + 1).": FAILED (HTTP {$httpCode} - {$statusLabel}) — {$msg}\n";
        } else {
            echo 'Request '.($i + 1).": FAILED (HTTP {$httpCode} - {$statusLabel})\n";
        }
    }

    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}

curl_multi_close($mh);

echo "\n========== STRESS TEST RESULTS ==========\n";
echo "Successful bookings: {$successCount}\n";
echo "Failed bookings: {$failureCount}\n";
echo "Total requests: {$totalRequests}\n";
echo 'Success rate: '.round(($successCount / max($totalRequests, 1)) * 100, 2)."%\n\n";

$statusCodes = array_count_values(array_map(fn (array $r): int => (int) $r['status'], $results));
echo "Status code distribution:\n";
foreach ($statusCodes as $code => $count) {
    $statusLabel = match ($code) {
        201 => 'Created',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        429 => 'Rate Limited',
        401 => 'Unauthorized',
        default => 'Other',
    };
    echo "  HTTP {$code} ({$statusLabel}): {$count} requests\n";
}

echo "\n";

// F-03 hard gate: ANY HTTP 500 among the losers is a stop-ship leak.
// A single winner does not absolve a 500 — that means the controller failed
// to translate a concurrent conflict (23P01) into 409 and instead leaked
// a server error (or worse, an unhandled exception).
$serverErrors = array_values(array_filter(
    $results,
    fn (array $r): bool => (int) $r['status'] === 500
));
if (count($serverErrors) > 0) {
    echo 'TEST FAILED: '.count($serverErrors)." request(s) returned HTTP 500 — concurrent conflict was not mapped cleanly.\n";
    echo "First 500 response body:\n";
    $body = $serverErrors[0]['response'];
    echo '  Request '.$serverErrors[0]['request'].': '.substr((string) $body, 0, 500)."\n";
    exit(1);
}

if ($successCount === 1) {
    echo "TEST PASSED: exactly 1 booking succeeded — pessimistic locking is valid.\n";
    exit(0);
}

if ($successCount > 1) {
    echo "TEST FAILED: {$successCount} bookings succeeded for one room/date range (expected 1).\n";
    exit(1);
}

// No booking succeeded — check if it's due to rate limiting or auth issues
$nonRateLimited = array_filter($results, fn (array $r): bool => $r['status'] !== 429);
if (count($nonRateLimited) === 0) {
    echo "TEST SKIPPED: all requests were rate-limited (429). Cannot validate locking.\n";
    exit(0);
}

echo "TEST FAILED: no booking succeeded.\n";
echo "First non-429 response:\n";
foreach ($nonRateLimited as $r) {
    echo "  Request {$r['request']}: HTTP {$r['status']} — ".substr((string) $r['response'], 0, 200)."\n";
    break;
}
exit(1);
