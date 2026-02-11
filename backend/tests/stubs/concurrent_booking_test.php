<?php

/**
 * Concurrent Booking Stress Test (50 simultaneous requests)
 *
 * Tests that pessimistic locking prevents double-booking
 * Simulates 50 concurrent users trying to book same room
 *
 * Usage:
 * php tests/stubs/concurrent_booking_test.php
 *
 * Expected result:
 * - 1 booking succeeds
 * - 49 bookings fail with 409 Conflict or 422 Unprocessable Entity
 */
$apiUrl = 'http://localhost:8000/api';
$roomId = 1; // Test with first room
$totalRequests = 50;
$successCount = 0;
$failureCount = 0;
$results = [];

echo "🔥 Starting concurrent booking stress test...\n";
echo "📋 Total concurrent requests: $totalRequests\n";
echo "🏨 Room ID: $roomId\n\n";

// Generate test date range
$checkIn = (new DateTime)->modify('+5 days')->format('Y-m-d');
$checkOut = (new DateTime)->modify('+7 days')->format('Y-m-d');

echo "📅 Date range: $checkIn to $checkOut\n";
echo "⏳ Sending requests...\n\n";

// Parallel curl requests using curl_multi
$mh = curl_multi_init();
$handles = [];

for ($i = 0; $i < $totalRequests; $i++) {
    $ch = curl_init();

    $guestEmail = 'stress-test-'.uniqid()."-user$i@example.com";
    $payload = json_encode([
        'room_id' => $roomId,
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'guest_name' => "Stress Test User $i",
        'guest_email' => $guestEmail,
        'guest_phone' => '+84912345678',
    ]);

    curl_setopt_array($ch, [
        CURLOPT_URL => "$apiUrl/bookings",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
    ]);

    curl_multi_add_handle($mh, $ch);
    $handles[$i] = $ch;
}

// Execute all requests simultaneously
$running = null;
do {
    curl_multi_exec($mh, $running);
    usleep(100000); // 100ms delay between checks
} while ($running > 0);

// Process results
for ($i = 0; $i < $totalRequests; $i++) {
    $ch = $handles[$i];
    $response = curl_multi_getcontent($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $results[] = [
        'request' => $i + 1,
        'status' => $httpCode,
        'response' => $response,
    ];

    if ($httpCode === 201) {
        $successCount++;
        echo '✅ Request '.($i + 1).": SUCCESS (HTTP 201)\n";
    } else {
        $failureCount++;
        $statusLabel = match ($httpCode) {
            409 => 'Conflict (Double-booking prevented)',
            422 => 'Unprocessable Entity (Validation failed)',
            429 => 'Too Many Requests (Rate limited)',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            500 => 'Server Error',
            default => 'Unknown',
        };
        echo '❌ Request '.($i + 1).": FAILED (HTTP $httpCode - $statusLabel)\n";
    }

    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}

curl_multi_close($mh);

// Print summary
echo "\n";
echo "========== STRESS TEST RESULTS ==========\n";
echo "✅ Successful bookings: $successCount\n";
echo "❌ Failed bookings: $failureCount\n";
echo "📊 Total requests: $totalRequests\n";
echo '✓ Success rate: '.round(($successCount / $totalRequests) * 100, 2)."%\n\n";

// Analyze results
$statusCodes = array_count_values(array_map(fn ($r) => $r['status'], $results));
echo "Status code distribution:\n";
foreach ($statusCodes as $code => $count) {
    $statusLabel = match ($code) {
        201 => 'Created (Booking successful)',
        409 => 'Conflict (Double-booking prevented)',
        422 => 'Unprocessable Entity',
        429 => 'Rate Limited',
        default => 'Other',
    };
    echo "  HTTP $code ($statusLabel): $count requests\n";
}

// Test outcome
echo "\n";
if ($successCount === 1 && $failureCount === $totalRequests - 1) {
    echo "🎯 TEST PASSED: Pessimistic locking working correctly!\n";
    echo "   ✓ Exactly 1 booking succeeded\n";
    echo "   ✓ 49 bookings blocked (double-booking prevented)\n";
    exit(0);
} elseif ($successCount > 1) {
    echo "❌ TEST FAILED: Multiple bookings succeeded (double-booking NOT prevented!)\n";
    echo "   This indicates pessimistic locking is not working correctly.\n";
    exit(1);
} else {
    echo "⚠️  TEST WARNING: No bookings succeeded\n";
    echo "   Check if the server is running and database is accessible.\n";
    exit(1);
}
