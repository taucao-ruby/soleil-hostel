# MoMo T4 — Execution Prompt for Opus 4.8

> Paste **everything inside the code fence below** into the executor. Self-contained, grounded in the
> current Soleil Hostel tree, scoped to **T4 only** (two new files: an Eloquent model + one additive
> migration). The UNIQUE(`order_id`,`trans_id`) constraint is the IPN linearization point — treat it as
> the load-bearing element, not a nicety.

````text
<role>
You are a senior Laravel 12 / PHP 8.3 backend engineer executing inside the Soleil Hostel monorepo.
You port existing house patterns faithfully, you understand that an INSERT-first UNIQUE constraint is
how this codebase makes webhook handling idempotent, and you treat CLAUDE.md + its decision order as
binding. Minimum correct diff, proven with a real migrate up/down on PostgreSQL.
</role>

<context>
You are executing task **T4** of `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` — the IPN idempotency
ledger for the additive MoMo sandbox payment path. T4 is independent of T3 (parallel) and is consumed by
T5 (handler) and T6 (controller does the INSERT-first dedup). T1/T2 (and likely T3) are already done.

Authority order (higher wins): CLAUDE.md → docs/agents/CONTRACT.md → the execution plan → this prompt.
Unresolvable conflict → stop and surface as `UNRESOLVED`.

This table is the durable record that lets a replayed MoMo IPN be a no-op: the controller INSERTs a
`processing` row keyed by (`order_id`,`trans_id`) BEFORE touching the booking; a duplicate INSERT raises
`UniqueConstraintViolationException`, which the controller treats as "already seen ⇒ 204 ack". Exactly the
posture `stripe_webhook_events.stripe_event_id` gives the Stripe path.
</context>

<task>
Create TWO new files:
1. `backend/app/Models/MoMoWebhookEvent.php` — `final class MoMoWebhookEvent extends Model`, ported from
   `StripeWebhookEvent` but LEAN (only the dedup-ledger surface, not the reconciliation reaper).
2. `backend/database/migrations/{ts}_create_momo_webhook_events_table.php` — one additive table.

Create only these two files. Do NOT create a factory, the handler (T5), the controller (T6), routes (T7),
or tests (T8). Do NOT add a foreign key to `bookings` or alter any existing table.
</task>

<authoritative_references>
Inspect these first; port conventions from the live files.

1. `backend/app/Models/StripeWebhookEvent.php` — the model to port: `declare(strict_types=1)`,
   `final class ... extends Model`, `use HasFactory`, `ERROR_MAX_LENGTH = 1000`, `$fillable`, `$casts`
   (`payload => 'array'`, datetimes), `markProcessed()`, `markFailed(Throwable|string|null)`, and the
   `private static sanitizeError()` redaction+truncation. PORT ONLY THESE. Do NOT port the reconciliation
   surface (`RECONCILABLE_TYPES`, `recordReconciliationError`, `markReconciliationExhausted`,
   `scopeStaleProcessing`, `scopeReconciliationExhausted`, `paymentIntentId`) — MoMo has no reaper in this plan.
2. `backend/database/migrations/2026_04_28_000001_create_stripe_webhook_events_table.php` — the create-table
   pattern: `$driver = DB::getDriverName();`, `jsonb` for pgsql else `json`, and the pgsql-only status CHECK
   constraint via `DB::statement(...)`. Mirror this structure exactly (driver branch + CHECK).
3. `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` §3 T4 (column list + acceptance) and §4 (the forged/replayed-
   IPN containment row that this constraint backs).
</authoritative_references>

<correctness_traps>
T1 — **Table name.** `Str::snake('MoMoWebhookEvent')` is `mo_mo_webhook_event` → Eloquent would infer the
table `mo_mo_webhook_events`, which does NOT match the migration's `momo_webhook_events`. You MUST set
`protected $table = 'momo_webhook_events';` on the model. (StripeWebhookEvent didn't need this; MoMo does
because of the internal capital.)

T2 — **NULLs defeat the unique key.** In PostgreSQL, multiple rows with a NULL in a UNIQUE column do NOT
collide. So `order_id` and `trans_id` (the dedup key) MUST be `NOT NULL`. A nullable `trans_id` would let
forged/replayed IPNs slip past dedup.

T3 — **Migration timestamp.** Generate the file with `php artisan make:migration create_momo_webhook_events_table`
so the timestamp is current (must sort AFTER 2026_05_18_*). Then replace the body. Do not hand-type a stale ts.
</correctness_traps>

<implementation_spec>
**Migration** (`{ts}_create_momo_webhook_events_table.php`) — anonymous `return new class extends Migration`,
`declare(strict_types=1)`, driver-aware like the Stripe create migration:

    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::create('momo_webhook_events', function (Blueprint $table) use ($driver): void {
            $table->id();
            $table->string('order_id');                 // NOT NULL — dedup key part 1
            $table->string('request_id')->nullable();
            $table->string('trans_id');                 // NOT NULL — dedup key part 2
            $table->string('type', 100);
            $table->string('status', 32);               // processing | processed | failed
            $table->integer('result_code')->nullable(); // MoMo resultCode (0 = success)

            if ($driver === 'pgsql') {
                $table->jsonb('payload');
            } else {
                $table->json('payload');
            }

            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'trans_id'], 'uq_momo_webhook_events_order_trans');
        });

        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE momo_webhook_events ADD CONSTRAINT chk_momo_webhook_events_status
                CHECK (status IN ('processing', 'processed', 'failed'))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('momo_webhook_events');
    }

Keep column order as the plan lists it. `dropIfExists` cleanly removes the table + its unique index + CHECK,
so no manual constraint drop is needed in `down()`.

**Model** (`MoMoWebhookEvent.php`) — `namespace App\Models;`, `final class MoMoWebhookEvent extends Model`,
`use HasFactory;` (parity; the factory itself is a T8 concern, not T4):

- `protected $table = 'momo_webhook_events';`  ← see correctness trap T1.
- `public const ERROR_MAX_LENGTH = 1000;`
- `protected $fillable = ['order_id','request_id','trans_id','type','status','result_code','payload','processed_at','failed_at','error'];`
- `protected $casts = ['payload' => 'array', 'result_code' => 'integer', 'processed_at' => 'datetime', 'failed_at' => 'datetime'];`
- `markProcessed(): void` — identical to Stripe: status='processed', processed_at=now(), error=null, failed_at=null.
- `markFailed(Throwable|string|null $error = null): void` — status='failed', failed_at=now(), error=self::sanitizeError($error).
- `private static function sanitizeError(Throwable|string|null $error): ?string` — port the Stripe structure,
  but redact MoMo-relevant secrets (this is the security adaptation the plan asks for — "hash_hmac/secret patterns"):

      $message = $error instanceof Throwable ? $error->getMessage() : $error;
      // ... null/'' guards as in Stripe ...
      $secret = config('services.momo.secret_key');
      if (is_string($secret) && $secret !== '') {
          $message = str_replace($secret, '[REDACTED_SECRET]', $message);
      }
      $patterns = [
          '/"signature"\s*:\s*"[A-Fa-f0-9]{64}"/' => '"signature":"[REDACTED]"',
          '/\bsignature=[A-Fa-f0-9]{64}\b/'       => 'signature=[REDACTED]',
          '/\b[A-Fa-f0-9]{64}\b/'                 => '[REDACTED_HMAC]',
          '/"accessKey"\s*:\s*"[^"]+"/'           => '"accessKey":"[REDACTED]"',
          '/\baccessKey=[A-Za-z0-9]+/'            => 'accessKey=[REDACTED]',
      ];
      // preg_replace, then mb_substr truncation to ERROR_MAX_LENGTH — exactly as Stripe does.

  Rationale: a MoMo failure message can echo back the signed payload; the 64-hex rule scrubs raw HMAC
  signatures and the secret-value replace scrubs the shared key, so neither lands in dashboards/audit exports.

Imports: `Illuminate\Database\Eloquent\Model`, `Illuminate\Database\Eloquent\Factories\HasFactory`, `Throwable`.
Do NOT import or reference `Booking` — the order↔booking link is the parsed `order_id` (T3), never a FK.
</implementation_spec>

<constraints>
- Additive only: 2 new files; no FK to `bookings`; no edits to `bookings` or any existing table/model.
  New files only ⇒ soleil-ai-review-engine impact analysis not required (plan §0). If you find a reason to
  edit an existing symbol, STOP.
- `payload` is jsonb on pgsql (the suite is PostgreSQL-only). Keep the driver branch for parity, but the
  acceptance DB is `soleil_test` (Postgres).
- No `env()` (the model reads `config('services.momo.secret_key')` only inside `sanitizeError`). No secrets
  committed. No `--no-verify`.
</constraints>

<acceptance_criteria>
1. `php artisan migrate` applies cleanly and `migrate:rollback` reverses it cleanly (up+down) on PostgreSQL.
2. A second insert with the same (`order_id`,`trans_id`) raises
   `Illuminate\Database\UniqueConstraintViolationException` (the INSERT-first linearization point works).
3. `MoMoWebhookEvent` resolves to table `momo_webhook_events`; `markProcessed()`/`markFailed()` flip status and
   stamp the timestamps; `sanitizeError` redacts a 64-hex HMAC and the configured secret.
4. Exactly 2 new files in the diff; no existing migration/model/table touched.
</acceptance_criteria>

<verification>
Run from repo root / `backend/` (test DB required — see plan P1/P2):

    docker compose up -d db
    cd backend && php scripts/check-test-db.php           # GATE-0

    php -l app/Models/MoMoWebhookEvent.php
    composer lint && vendor/bin/phpstan analyse app/Models/MoMoWebhookEvent.php

    php artisan migrate                                   # applies the new table
    php artisan tinker --execute="\$r=['order_id'=>'soleil-1-x','request_id'=>'req-1','trans_id'=>'99','type'=>'momo.ipn','status'=>'processing','result_code'=>0,'payload'=>[]]; App\Models\MoMoWebhookEvent::create(\$r); try { App\Models\MoMoWebhookEvent::create(\$r); echo 'NO-THROW(BAD)'; } catch (\Illuminate\Database\UniqueConstraintViolationException \$e) { echo 'UNIQUE-OK'; }"
    php artisan migrate:rollback --step=1                 # down() is clean
    php artisan migrate                                   # re-apply so the suite/DB is consistent

    composer test                                         # full suite green (migrates soleil_test fresh)
    git --no-pager diff --stat                            # exactly 2 new files

The durable idempotent-replay proof (valid IPN twice ⇒ confirmed once, 204 ack) lands in T8; T4 only proves
the constraint + migration mechanics.
</verification>

<output_format>
Follow CLAUDE.md output-style policy: change under `.claude/output-styles/execution-plan.md`, results under
`.claude/output-styles/execution.md`. Tag findings `[CONFIRMED]`, `[INFERRED]`, `[UNPROVEN]`, `[ACTION]`.
End with the `git diff` plus the migrate up/down + UNIQUE-OK tinker output as evidence.
</output_format>

<stop_conditions>
Stop and confirm with me before: creating/editing any file beyond the model + migration; adding a FK to or
altering `bookings` (forbidden); making `order_id`/`trans_id` nullable; porting the reconciliation reaper
surface; or committing. Do NOT commit, push, or merge — continue branch `feature/momo-sandbox-payment`, leave
the change uncommitted, and show me the diff + migrate/UNIQUE verification output.
</stop_conditions>
````
