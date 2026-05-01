<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

/**
 * Architecture invariant: rooms.status is owned exclusively by Room::scopeBookable().
 *
 * Batch 4 / 3B — phase 3 of the rooms.status deprecation. The canonical
 * bookability predicate is Room::scopeBookable(). Every other path that
 * needed "available rooms" was migrated to chain through scopeBookable()
 * (or scopeReady() for pure physical readiness checks).
 *
 * This test pins the rule so accidental reintroduction of `->where('status', 'available')`
 * on the rooms table — or new callers of Room::active() in app/ — fail CI.
 *
 * Allowed exceptions:
 *   - Room::scopeBookable() itself (the single owner of the column read).
 *   - Room::scopeActive() definition (deprecated, no-status implementation).
 *   - Booking-related code (different table, same column name; out of scope).
 */
class RoomsStatusUsageTest extends TestCase
{
    /** @return list<string> */
    private function phpFilesUnder(string $relPath): array
    {
        $base = realpath(__DIR__.'/../../../'.$relPath);
        if ($base === false || ! is_dir($base)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        /** @var \SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isFile() && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        return $files;
    }

    public function test_no_caller_in_app_uses_room_scope_active(): void
    {
        $offenders = [];

        foreach ($this->phpFilesUnder('app') as $file) {
            // Skip the scope definition itself — it lives in app/Models/Room.php.
            if (str_ends_with($file, 'app'.DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR.'Room.php')
                || str_ends_with($file, 'app/Models/Room.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file);

            // Match Room::active() and chained ->active() that follow Room::query()/Room::.
            // We accept the false-positive risk of e.g. Booking::active() — that scope is
            // legitimate; the regex below targets the Room namespace explicitly.
            if (preg_match('/\bRoom::\s*active\s*\(/', $contents)
                || preg_match('/\bRoom::query\(\)\s*->\s*active\s*\(/', $contents)
                || preg_match('/Room\\\\.*->\s*active\s*\(/', $contents)) {
                $offenders[] = str_replace('\\', '/', $file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "No caller in app/ may use Room::active(). Migrate to Room::bookable().\nOffenders:\n  - "
                .implode("\n  - ", $offenders)
        );
    }

    public function test_no_raw_rooms_status_filter_in_app(): void
    {
        $offenders = [];
        $allowedFiles = [
            // The single owner of the status read. Defines the canonical predicate.
            'app/Models/Room.php',
        ];

        foreach ($this->phpFilesUnder('app') as $file) {
            $relative = str_replace(
                '\\',
                '/',
                substr($file, strpos($file, 'backend'.DIRECTORY_SEPARATOR.'app') + strlen('backend/'))
            );
            // Accept either separator style.
            $relativeNormalised = str_replace('\\', '/', $relative);

            if (in_array($relativeNormalised, $allowedFiles, true)) {
                continue;
            }

            $contents = (string) file_get_contents($file);

            // Catch ->where('status', 'available') and ->where('status','available') variants
            // chained on the rooms table. We bias on the `'available'` literal because
            // bookings/reviews use different status values; the false-positive surface
            // is small and worth the tighter signal.
            if (preg_match("/->\s*where\s*\(\s*['\"]status['\"]\s*,\s*['\"]available['\"]/", $contents)) {
                $offenders[] = $relativeNormalised;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "No file in app/ may filter rooms by raw status='available'. Use Room::bookable() (the canonical predicate).\nOffenders:\n  - "
                .implode("\n  - ", $offenders)
        );
    }
}
