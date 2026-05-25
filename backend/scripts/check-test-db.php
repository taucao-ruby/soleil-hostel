<?php

declare(strict_types=1);

/**
 * GATE-0 — PostgreSQL test-database readiness preflight.
 *
 * The backend test suite boots RefreshDatabase against PostgreSQL, so an
 * unreachable server surfaces mid-run as an opaque 34-frame QueryException
 * ("SQLSTATE[08006] ... Connection refused") that hides the real problem.
 * This gate runs BEFORE migrations/tests and collapses that into one early,
 * actionable line.
 *
 * It connects with plain PDO (no framework boot) using the same DB_* variables
 * phpunit.xml / CI set, so it probes exactly the database the suite will use.
 * The defaults below intentionally mirror backend/phpunit.xml so a bare
 * `php scripts/check-test-db.php` from a developer shell checks the right DB.
 *
 * The password is never written to output.
 *
 *   GATE-0-PASSED  -> exit 0   server reachable AND test database present
 *   GATE-0-BLOCKED -> exit 2   driver missing, server down, or database absent
 */

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$db = getenv('DB_DATABASE') ?: 'soleil_test';
$user = getenv('DB_USERNAME') ?: 'soleil';
$passEnv = getenv('DB_PASSWORD');
$pass = $passEnv === false ? 'secret' : $passEnv;

// Password intentionally omitted — this is the only location string we print.
$target = sprintf('pgsql://%s@%s:%s/%s', $user, $host, $port, $db);

if (! extension_loaded('pdo_pgsql')) {
    fwrite(STDERR, "GATE-0-BLOCKED: PHP extension pdo_pgsql is not loaded; cannot reach the PostgreSQL test DB.\n");
    fwrite(STDERR, "  Fix: enable pdo_pgsql in php.ini, or run the suite inside the Docker backend container.\n");
    exit(2);
}

$connect = static function (string $dbname) use ($host, $port, $user, $pass): void {
    new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbname),
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );
};

try {
    $connect($db);
    fwrite(STDOUT, "GATE-0-PASSED: PostgreSQL test DB reachable ({$target})\n");
    exit(0);
} catch (Throwable $dbError) {
    // Distinguish "server down" from "server up, database missing" by probing the
    // always-present 'postgres' maintenance database with the same credentials.
    try {
        $connect('postgres');
    } catch (Throwable $serverError) {
        fwrite(STDERR, "GATE-0-BLOCKED: PostgreSQL server unreachable at {$host}:{$port}.\n");
        fwrite(STDERR, "  Reason: {$serverError->getMessage()}\n");
        fwrite(STDERR, "  Fix: start it (from repo root): docker compose up -d db\n");
        exit(2);
    }

    fwrite(STDERR, "GATE-0-BLOCKED: PostgreSQL server is up but test database '{$db}' is missing.\n");
    fwrite(STDERR, "  Reason: {$dbError->getMessage()}\n");
    fwrite(STDERR, "  Fix: docker compose exec -T db createdb -U {$user} {$db}\n");
    exit(2);
}
