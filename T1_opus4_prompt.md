# Opus 4 Execution Prompt — T-1: In-Suite PDO Lock Blocking Test

> **Target model**: `claude-opus-4-6`
> **Send as**: single user message (no system prompt needed — context is self-contained)
> **Do NOT edit** the constraint or acceptance-criteria sections before sending.

---

## PROMPT (copy everything below this line)

---

You are an expert PostgreSQL concurrency engineer and Laravel PHPUnit specialist. Your task is to write one production-ready test method (and its enclosing class) for the Soleil Hostel booking system. This is a precise implementation task — no research, no alternatives. Produce correct, runnable code on the first pass.

---

<mission>
Implement **T-1**: an in-suite PHPUnit test that opens a second independent PDO connection to the same PostgreSQL database, issues an interleaved `SELECT … FOR UPDATE` against a row already locked by the first connection, and asserts that the loser connection **blocks** (waits for the lock) and then **fails** with a PostgreSQL lock-timeout error.

This test pairs with T-0 (which proves the winner acquires the lock). Together they prove the pessimistic-locking mechanism in `CreateBookingService` works end-to-end.
</mission>

---

<codebase_facts>
**Runtime environment**
- Framework: Laravel 11 / PHP 8.3
- Test runner: `php artisan test` from `backend/` (PHPUnit 11)
- Database driver: **PostgreSQL only** — MySQL SQLSTATE codes do NOT apply
- Test DB config (from `backend/phpunit.xml`):
  - `DB_CONNECTION=pgsql`
  - `DB_HOST=127.0.0.1`
  - `DB_PORT=5432`
  - `DB_DATABASE=soleil_test`
  - `DB_USERNAME=soleil`
  - `DB_PASSWORD=secret`

**PostgreSQL SQLSTATE codes relevant to this test**
| Error | SQLSTATE | Trigger |
|---|---|---|
| Lock timeout | `55P03` | `lock_timeout` session var exceeded |
| Deadlock | `40P01` | Cycle detected |
| Serialization failure | `40001` | SERIALIZABLE conflict |

**The lock timeout SQLSTATE `55P03` surfaces as:**
- `QueryException::getCode()` → `'55P03'` (string)
- `QueryException::getMessage()` contains `'canceling statement due to lock timeout'`

**Existing test conventions**
- Namespace: `Tests\Feature\Booking`
- Extends: `Tests\TestCase`
- Trait: `Illuminate\Foundation\Testing\RefreshDatabase`
- File location: `backend/tests/Feature/Booking/`
- No `@runInSeparateProcess` — all tests run in the same PHP process

**Key models and scopes**
- `Booking::scopeWithLock(Builder $query): Builder` → alias for `->lockForUpdate()`
- `Booking::scopeOverlappingBookings(Builder $query, int $roomId, $checkIn, $checkOut): Builder`
- `Booking::factory()` creates a booking with `Room::factory()` and `User::factory()` wired in automatically

**`CreateBookingService::classifyDatabaseError()`** (do NOT change this — just understand it)
```php
// SQLSTATE is checked from $e->errorInfo[0] ?? $e->getCode()
// '40P01' or message contains 'deadlock' → 'deadlock'
// '40001' → 'serialization'
// message contains 'lock wait timeout' OR 'lock timeout' → 'lock_timeout'
// else → 'other'
```
Note: `55P03` is classified via **message match** (`'lock timeout'`), not via explicit SQLSTATE check.
This is a known gap in the classifier — but the test asserts on the raw SQLSTATE `'55P03'` from
the exception, which is the correct ground-truth value.
</codebase_facts>

---

<task_specification>
Write a **single PHP file** at path `backend/tests/Feature/Booking/PdoLockBlockingTest.php` containing:

### Test class: `PdoLockBlockingTest`

#### Method: `test_second_pdo_connection_blocks_on_held_lock_then_fails()`

**Exact execution sequence:**

```
Step 1 — Arrange
  Create a Booking row via Booking::factory()->create()
  Store its ID

Step 2 — Acquire lock on Connection A (the winner)
  Register an ephemeral second connection config at runtime (see constraint C-2)
  Begin a transaction on the DEFAULT pgsql connection
  Execute: Booking::query()->where('id', $bookingId)->withLock()->first()
  → This acquires SELECT … FOR UPDATE on the row, held in an open transaction
  → DO NOT commit yet

Step 3 — Attempt lock on Connection B (the loser)
  Begin a transaction on the ephemeral 'pgsql_loser' connection
  INSIDE that transaction, execute: SET LOCAL lock_timeout = '2s'
  Record $start = microtime(true)
  Attempt: DB::connection('pgsql_loser')->table('bookings')
             ->where('id', $bookingId)->lockForUpdate()->first()
  → This BLOCKS waiting for Connection A's lock

Step 4 — Assert loser blocked then failed
  Wrap Step 3 in try/catch(\Illuminate\Database\QueryException $e)
  Inside catch:
    $elapsed = microtime(true) - $start
    Assert $e->getCode() === '55P03'
    Assert str_contains($e->getMessage(), 'lock timeout')
    Assert $elapsed >= 1.8  (proves it blocked for ~2 seconds, not returned immediately)
  If NO exception is thrown, call $this->fail(...)

Step 5 — Clean up (in finally)
  Rollback Connection A: DB::connection('pgsql')->rollBack()  // releases the lock
  If Connection B transaction is open: DB::connection('pgsql_loser')->rollBack()
```

#### Method: `tearDown(): void`

Override `tearDown` to guarantee cleanup even on test failure:
```php
// Rollback any open transactions on both connections
// Purge 'pgsql_loser' from Laravel's connection manager
// Call parent::tearDown()
```
</task_specification>

---

<constraints>
**C-1 — Serial-safe, no parallelism**
Do NOT use `pcntl_fork`, `Process::start()`, `proc_open`, threads, or any async mechanism.
All steps execute sequentially in one PHP process. The "blocking" is real: PHP blocks
synchronously on Connection B's `lockForUpdate()` call until PostgreSQL's `lock_timeout`
fires and throws.

**C-2 — Runtime second connection (no config file edits)**
Do NOT add a permanent key to `config/database.php`. Create the second connection at runtime
inside the test:
```php
$primaryConfig = config('database.connections.' . config('database.default'));
config(['database.connections.pgsql_loser' => $primaryConfig]);
DB::purge('pgsql_loser'); // guarantee a fresh PDO instance, not a reused one
```
After this, `DB::connection('pgsql_loser')` gives a second independent TCP connection to PostgreSQL.

**C-3 — SET LOCAL must be inside a transaction**
PostgreSQL's `SET LOCAL` is scoped to the current transaction block.
Issue it AFTER `beginTransaction()` on the loser connection:
```php
DB::connection('pgsql_loser')->beginTransaction();
DB::connection('pgsql_loser')->statement("SET LOCAL lock_timeout = '2s'");
// NOW issue the lockForUpdate — it will timeout after 2s
```

**C-4 — RefreshDatabase compatibility**
`RefreshDatabase` wraps each test in a transaction on the default connection.
Connection A MUST use `DB::connection('pgsql')` (or `DB::connection()`), so it participates in
the outer RefreshDatabase transaction via savepoint. Even inside a savepoint, `SELECT … FOR UPDATE`
acquires a real row lock that a separate PDO session will contend with.
Connection B MUST use the separate `pgsql_loser` connection — a genuinely independent PDO session.

**C-5 — Timing assertion tolerance**
Use `$this->assertGreaterThanOrEqual(1.8, $elapsed)`.
Do not assert an upper bound — CI machines vary in speed.

**C-6 — No test flakiness**
2 seconds is the minimum safe lock_timeout for CI.
Do not use 100 ms or 500 ms.

**C-7 — Follow existing test style**
No `var_dump`, no `echo`. Assertions only. Match the code style of `ConcurrentBookingTest.php`
and `RoomOptimisticLockingTest.php` already in the suite.
</constraints>

---

<acceptance_criteria>
The test passes if and only if ALL of the following hold:

- [ ] `QueryException` is caught (not raw `PDOException`)
- [ ] `$e->getCode() === '55P03'` — exact SQLSTATE string
- [ ] `str_contains($e->getMessage(), 'lock timeout')` — PostgreSQL error text present
- [ ] `$elapsed >= 1.8` — Connection B genuinely waited ~2 seconds
- [ ] Connection A's transaction is rolled back in `finally`
- [ ] `DB::connection('pgsql_loser')` is purged in `tearDown`
- [ ] `php artisan test --filter=test_second_pdo_connection_blocks_on_held_lock_then_fails` exits green
- [ ] `php artisan test tests/Feature/Booking/PdoLockBlockingTest.php` exits green
- [ ] No modification to `config/database.php`, `phpunit.xml`, or any non-test file
</acceptance_criteria>

---

<thinking>
Before writing the code, reason through these points explicitly:

1. **RefreshDatabase + second connection**: RefreshDatabase wraps the test in a transaction on
   the default connection. If Connection B uses the SAME default connection, it is the same PDO
   session and cannot contend with itself — you must use a separate PDO via `pgsql_loser`.
   Connection B's data is NOT wrapped by RefreshDatabase, but since we only READ (lockForUpdate)
   and always rollback B, there are no orphaned writes.

2. **Transaction nesting / savepoints**: `DB::beginTransaction()` inside a RefreshDatabase test
   issues a SAVEPOINT, not a real BEGIN. However, `SELECT … FOR UPDATE` still acquires real row
   locks even inside a savepoint. Connection B (separate PDO) will genuinely contend with that lock.

3. **`SET LOCAL lock_timeout` scope**: Setting is transaction-scoped. After Connection B's
   transaction is rolled back (in finally), the setting is gone automatically. No session-level
   cleanup needed.

4. **Exception hierarchy**: `QueryException::getCode()` returns the SQLSTATE string copied from
   the inner PDOException. Assert on `QueryException`, not `PDOException`, to match Laravel
   conventions used everywhere else in this test suite.

5. **`DB::purge('pgsql_loser')` placement**: Call it in BOTH setUp (before first use) and
   tearDown (to release the TCP connection). This prevents state leaking between test runs when
   the test class is re-instantiated.
</thinking>

---

<output_instructions>
Produce exactly **three sections**:

### Section 1 — Complete PHP file
The full contents of `backend/tests/Feature/Booking/PdoLockBlockingTest.php`.
Include all `use` imports, the class declaration, the test method, and `tearDown`.
The file must be complete and runnable with zero edits.

### Section 2 — Run commands
```bash
# Single test:
php artisan test --filter=test_second_pdo_connection_blocks_on_held_lock_then_fails

# Full class:
php artisan test tests/Feature/Booking/PdoLockBlockingTest.php
```

### Section 3 — Assumptions (≤ 5 bullets)
Only if a genuine assumption was required (e.g., factory state needed).
Skip entirely if everything is determined by the constraints above.

**Do not produce** prose walkthroughs, alternative implementations, or explanations
beyond Sections 1–3.
</output_instructions>
