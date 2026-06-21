# MoMo T2 — Execution Prompt for Opus 4.8

> Paste **everything inside the code fence below** into the executor. Self-contained, grounded in the
> current Soleil Hostel tree, scoped to **T2 only** (two new leaf types — a readonly DTO + a string
> enum). Pure types: no env, no I/O, no wiring into T3/T5 yet.

````text
<role>
You are a senior Laravel 12 / PHP 8.3 backend engineer executing inside the Soleil Hostel monorepo.
You work additively, inspect before you change, mirror existing house style exactly, and treat
CLAUDE.md and its decision order as binding. You write the minimum correct diff and prove it.
</role>

<context>
You are executing task **T2** of `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` — the typed contracts
for the additive MoMo (sandbox) payment path that runs fully parallel to Stripe. T2 depends on nothing
and is consumed later by T3 (`MoMoService`) and T5 (`MoMoIpnHandler`). T1 (the `services.momo` config
block) is already done.

Authority order that governs this task (higher wins): CLAUDE.md → docs/agents/CONTRACT.md → the
execution plan → this prompt. If a conflict is unresolvable, stop and surface it as `UNRESOLVED`.

These two files are pure value types — the data contracts that every later MoMo task speaks. They carry
no behavior, no env reads, no I/O, no dependencies. Correctness here is "compiles, fully typed, matches
the Stripe-side siblings exactly."
</context>

<task>
Create two NEW files that mirror their Stripe-side siblings in the same namespace:
1. `backend/app/Services/Payment/MoMoPaymentStartResult.php` — a `final readonly` DTO with fields
   `orderId, requestId, payUrl, deeplink, qrCodeUrl, amount, currency`, mirroring `PaymentIntentStartResult`.
2. `backend/app/Services/Payment/MoMoIpnOutcome.php` — a backed `enum ... : string` with cases
   `Confirmed, AlreadyConfirmed, BookingNotFound, InvalidState, AmountMismatch`, mirroring
   `PaymentIntentApplyOutcome`.
Create only these two files. Do not wire them into any service, controller, route, or test — that is T3+.
</task>

<authoritative_references>
Inspect these first and copy their exact conventions — header, `declare(strict_types=1)`, namespace,
promoted constructor properties, `final readonly`, backed-enum style, per-case docblocks. Do not trust
this prompt's snippets over the live files.

1. `backend/app/Services/Payment/PaymentIntentStartResult.php` — the DTO pattern: `final readonly class`,
   promoted constructor params, nullable (`?string`) for optional fields, `int` amounts, no docblock noise,
   zero `use` imports.
2. `backend/app/Services/Payment/PaymentIntentApplyOutcome.php` — the enum pattern: `enum X: string`,
   snake_case string values, a class-level docblock plus a docblock on every case.
3. `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` §3 T2 (the spec) and §2 (the MoMo create/IPN field lists
   that justify the field/case names).
</authoritative_references>

<constraints>
- Additive only: exactly 2 NEW files, both under `namespace App\Services\Payment;`. No edits to any
  existing file — including the two reference siblings. Touch no existing symbol.
- Because these are brand-new files (no existing symbol modified), soleil-ai-review-engine impact
  analysis is not required for T2 (plan §0). If you find a reason to edit an existing symbol, STOP.
- Leaf types only: no `use` imports, no `env()`, no `config()`, no Booking/model/service references,
  no methods beyond the implicit enum API. Keep them dependency-free so T3/T5 can import them safely.
- `declare(strict_types=1);` at the top of both files (matches the siblings). TypeScript-strict mindset:
  every property and every case value explicitly typed; no mixed, no untyped.
- Never commit secrets (N/A here — no values), do not use `--no-verify`.
</constraints>

<implementation_spec>
**File 1 — `MoMoPaymentStartResult.php`** (mirror `PaymentIntentStartResult`):

    <?php

    declare(strict_types=1);

    namespace App\Services\Payment;

    /**
     * Result of starting a MoMo create-payment request — the redirect/QR/deeplink
     * channels plus the amount the guest is expected to pay. MoMo analogue of
     * PaymentIntentStartResult.
     */
    final readonly class MoMoPaymentStartResult
    {
        public function __construct(
            public string $orderId,
            public string $requestId,
            public string $payUrl,
            public ?string $deeplink,
            public ?string $qrCodeUrl,
            public int $amount,
            public string $currency,
        ) {}
    }

  Typing rationale (apply, don't blindly copy): `payUrl` is the guaranteed redirect on a successful
  create → non-null `string`; `deeplink` and `qrCodeUrl` are channel-dependent → `?string` (mirrors how
  the sibling makes `clientSecret` nullable). `amount` is an integer minor-unit/VND value → `int`
  (MoMo/VND has no decimals); `currency` is the ISO string. Keep the field order exactly as the T2 spec
  lists it.

**File 2 — `MoMoIpnOutcome.php`** (mirror `PaymentIntentApplyOutcome`):

    <?php

    declare(strict_types=1);

    namespace App\Services\Payment;

    /**
     * Outcome of applying a MoMo IPN (server→server payment notification) to the
     * local booking. Shared, audited contract between the MoMo IPN controller and
     * the IPN handler over what "the business effect happened" means — the MoMo
     * analogue of PaymentIntentApplyOutcome.
     */
    enum MoMoIpnOutcome: string
    {
        /** Booking transitioned PENDING → CONFIRMED. */
        case Confirmed = 'confirmed';

        /** Booking was already CONFIRMED for this order/transId; idempotent no-op. */
        case AlreadyConfirmed = 'already_confirmed';

        /** No local booking row maps to this MoMo orderId. */
        case BookingNotFound = 'booking_not_found';

        /**
         * Booking exists but is not PENDING, so auto-confirmation is forbidden
         * (e.g. cancelled, refund_pending). Confirming would silently violate the
         * state machine, so the caller must surface this for review.
         */
        case InvalidState = 'invalid_state';

        /**
         * Security guard: IPN reports success but the notified amount/currency does
         * not match the booking's expected amount. The booking is NOT confirmed and
         * the event must be surfaced — this is the anti-tamper check against a forged
         * or replayed under/over-payment notification.
         */
        case AmountMismatch = 'amount_mismatch';
    }

  Keep string values snake_case (consistent with the sibling). `AmountMismatch` is the one case with no
  Stripe sibling — its docblock frames it as the amount-integrity control, matching the plan's
  fail-closed posture for IPN handling (T5/T6).
</implementation_spec>

<acceptance_criteria>
1. Both files parse and autoload: `class_exists(MoMoPaymentStartResult::class)` and
   `enum_exists(MoMoIpnOutcome::class)` are true.
2. Fully typed: static analysis (Larastan/PHPStan, Psalm) reports no new errors; Pint reports no style
   diff. The DTO is `final readonly` with all 7 promoted props typed; the enum is backed `: string` with
   all 5 cases.
3. Zero behavioral change elsewhere: only these 2 new files appear in the diff; the two reference
   siblings are untouched.
</acceptance_criteria>

<verification>
Run from `backend/` (these are the repo's real gates — see `docs/COMMANDS_AND_GATES.md` and
`docs/agents/COMMANDS.md` for the authoritative gate list):

    php -l app/Services/Payment/MoMoPaymentStartResult.php
    php -l app/Services/Payment/MoMoIpnOutcome.php

    composer dump-autoload
    php artisan tinker --execute="var_dump(class_exists(\App\Services\Payment\MoMoPaymentStartResult::class), enum_exists(\App\Services\Payment\MoMoIpnOutcome::class));"
    # expect: bool(true) bool(true)

    composer lint                       # Pint --test: no style diff
    vendor/bin/phpstan analyse app/Services/Payment/MoMoPaymentStartResult.php app/Services/Payment/MoMoIpnOutcome.php
    # plus Psalm if the gate list requires it

No PHPUnit run is meaningful yet (nothing consumes these until T3/T5); do not add tests in T2.
git --no-pager diff --stat   # must list exactly the 2 new files
</verification>

<output_format>
Follow CLAUDE.md output-style policy: produce the change under `.claude/output-styles/execution-plan.md`
and report results under `.claude/output-styles/execution.md`. Tag every finding `[CONFIRMED]`,
`[INFERRED]`, `[UNPROVEN]`, or `[ACTION]`; untagged claims are a defect. End with the `git diff` and the
lint / static-analysis / tinker output as evidence.
</output_format>

<stop_conditions>
Stop and confirm with me before: creating or editing any file other than the two named; adding any
`use` import, method, env/config read, or dependency to these types; touching an existing symbol; or
committing. Do NOT commit, push, or merge — branch `feature/momo-sandbox-payment` (continue the T1
branch; create from `dev` if absent), leave the change uncommitted, and show me the diff + gate output.
</stop_conditions>
````
