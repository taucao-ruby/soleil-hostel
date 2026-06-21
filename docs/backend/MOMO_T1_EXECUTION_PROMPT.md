# MoMo T1 — Execution Prompt for Opus 4.8

> Paste **everything inside the code fence below** into the executor. It is self-contained,
> grounded in the current Soleil Hostel tree, and scoped to **T1 only** (config block + `.env.example`).
> The same skeleton extends to T2–T9 by swapping the `<task>` / `<implementation_spec>` blocks.

````text
<role>
You are a senior Laravel 11 backend engineer executing inside the Soleil Hostel monorepo
(Laravel API + React SPA). You work additively, inspect before you change, and treat
CLAUDE.md and its decision order as binding. You write the minimum correct diff and prove it.
</role>

<context>
You are executing task **T1** of `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` — the first,
dependency-free step of an additive MoMo (sandbox) direct-payment path that runs fully parallel
to Stripe and touches none of the booking-overlap or Stripe logic.

Authority order that governs this task (higher wins): CLAUDE.md → docs/agents/CONTRACT.md →
the execution plan → this prompt. If any conflict is unresolvable, stop and surface it as
`UNRESOLVED` rather than inventing a rule.

T1 is config-only. It adds a `services.momo` block and documents MoMo's PUBLIC sandbox values
in `.env.example`. It is the foundation every later task (T3 `MoMoService`, T6 controller) reads
via `config('services.momo.*')`.
</context>

<task>
Add a nullable `momo` block to `backend/config/services.php` that reads its values from env, and
document the public MoMo sandbox values in `backend/.env.example`. Mirror the existing
`services.stripe` bounded-timeout block exactly in style and posture. Change **only these two
files**. Do not write the service, controller, routes, migration, or tests — those are T2–T9.
</task>

<authoritative_references>
Inspect these first; do not trust this prompt's snippets over the live tree (CLAUDE.md:
"Inspect before changing. Do not guess.").

1. `backend/config/services.php` — the `stripe` block (≈ lines 38–56) is the pattern to mirror:
   bounded `(int) env('STRIPE_CONNECT_TIMEOUT', 2)` / `read_timeout` 5, with a header comment
   explaining why timeouts stay small (no call should hang a worker near a DB lock).
2. `backend/.env.example` — sectioned `# ========== NAME ==========` headers; the
   `# ========== STRIPE (Payment processing) ==========` section is ≈ lines 105–109. Append the
   MoMo section immediately after it.
3. `backend/app/Console/Commands/AssertProductionConfig.php` and
   `backend/tests/Feature/Operational/AssertProductionConfigTest.php` — confirm for yourself that
   **neither references `services.momo`**. They must stay that way: do NOT add a MoMo assertion.
4. `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` §2 (public sandbox creds) and §3 T1 (the spec).
</authoritative_references>

<constraints>
- `env()` is allowed ONLY inside `config/*.php` (this is Laravel's env boundary). Runtime code reads
  `config('services.momo.*')` — never `env()`. Do not "fix" the `env()` calls you add here.
- Never commit real secrets. The values you add are MoMo's PUBLISHED sandbox test credentials
  (partnerCode `MOMO`, accessKey `F8BBA842ECF85`, secretKey `K951B6PE1waDMi640xX08PD3vg6EkVlz`) —
  explicitly NOT secrets per the plan §2. They belong in `.env.example` (a template), not in
  committed PHP.
- Keep the block NULLABLE: any production-config assertion must never require a MoMo key. Credentials
  and deployment-specific URLs resolve to `null` when unset; do not add MoMo to AssertProductionConfig.
- Additive only. No edits to Stripe, booking, migrations, routes, or any existing symbol. This is a
  config array-literal edit, not a symbol edit, so soleil-ai-review-engine impact analysis is not
  required for T1 (plan §0). If you find yourself needing to touch any existing function/class/method,
  STOP and confirm first.
- Scope ceiling for T1 = exactly 2 files. Do not exceed it. Do not use `--no-verify`.
</constraints>

<implementation_spec>
Apply this secret-vs-constant rule (this is what "mirror Stripe" means here):
- Stable protocol constants get a baked default (like Stripe's numeric 2/5): `request_type` →
  `'captureWallet'`; `connect_timeout` → 2; `read_timeout` → 5 (cast `(int)`).
- Deployment-specific values (credentials + URLs + store id) read env with NO default → `null` when
  unset. This is what keeps the block nullable.

Target shape for `backend/config/services.php` (verify the surrounding block first, then add after
the `stripe` block):

    /*
    |--------------------------------------------------------------------------
    | MoMo (sandbox) HTTP client policy + credentials
    |--------------------------------------------------------------------------
    |
    | Additive, parallel sandbox payment path (MOMO_SANDBOX_EXECUTION_PLAN T1).
    | Same bounded-timeout posture as the Stripe block: a MoMo create/query call
    | must never hang a worker near a booking/room lock. Credentials and URLs read
    | from env and default to null when unset, so the block is intentionally
    | nullable — AssertProductionConfig requires none of these keys.
    |
    */

    'momo' => [
        'endpoint' => env('MOMO_ENDPOINT'),
        'partner_code' => env('MOMO_PARTNER_CODE'),
        'access_key' => env('MOMO_ACCESS_KEY'),
        'secret_key' => env('MOMO_SECRET_KEY'),
        'ipn_url' => env('MOMO_IPN_URL'),
        'redirect_url' => env('MOMO_REDIRECT_URL'),
        'store_id' => env('MOMO_STORE_ID'),
        'request_type' => env('MOMO_REQUEST_TYPE', 'captureWallet'),
        'connect_timeout' => (int) env('MOMO_CONNECT_TIMEOUT', 2),
        'read_timeout' => (int) env('MOMO_READ_TIMEOUT', 5),
    ],

Target section to append to `backend/.env.example` (right after the STRIPE section, matching the
existing `# ==========` header style):

    # ========== MOMO (Sandbox direct-payment path) ==========
    # Additive MoMo AIO v2 sandbox path — see docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md.
    # PUBLIC, MoMo-published sandbox test creds — shared across all sandbox users, NOT secrets.
    # Swap for your own MoMo Business sandbox keys for a clean transId history. Never commit real keys.
    MOMO_ENDPOINT=https://test-payment.momo.vn/v2/gateway/api/create
    MOMO_PARTNER_CODE=MOMO
    MOMO_ACCESS_KEY=F8BBA842ECF85
    MOMO_SECRET_KEY=K951B6PE1waDMi640xX08PD3vg6EkVlz
    MOMO_STORE_ID=SoleilHostel
    MOMO_REQUEST_TYPE=captureWallet
    # Server->server IPN + browser redirect targets. MoMo's IPN cannot reach localhost; for a live
    # demo expose a tunnel (e.g. ngrok) and set the public URL. Not needed for the test suite
    # (IPN is simulated). Align these paths with the T7 routes when they exist.
    MOMO_IPN_URL=http://localhost:8000/api/v1/payments/momo/ipn
    MOMO_REDIRECT_URL=http://localhost:5173/booking/momo/return
    # Bounded HTTP timeouts (seconds) — mirror STRIPE_* posture; keep small so a MoMo call never
    # hangs a worker near a booking/room lock.
    MOMO_CONNECT_TIMEOUT=2
    MOMO_READ_TIMEOUT=5

Match the file's actual indentation and quote style (single quotes, trailing commas, 4-space indent).
Do not reformat or reorder any existing keys.
</implementation_spec>

<acceptance_criteria>
1. `config('services.momo.partner_code')` resolves without error (returns the env value, or `null`
   when `MOMO_PARTNER_CODE` is unset). `config('services.momo')` returns the full 10-key array.
2. `config('services.momo.connect_timeout')` and `read_timeout` are integers (2 / 5 by default).
3. `AssertProductionConfigTest` still passes — the MoMo block adds no required production key.
4. Exactly 2 files changed: `backend/config/services.php`, `backend/.env.example`. No other diff.
</acceptance_criteria>

<verification>
Run from the repo root (PostgreSQL test DB required for the suite):

    docker compose up -d db
    cd backend && php scripts/check-test-db.php          # GATE-0 preflight

    php artisan config:clear                              # drop any cached config before tinker
    php artisan tinker --execute="var_export(config('services.momo'));"   # 10 keys, no error
    php artisan tinker --execute="var_dump(config('services.momo.connect_timeout'));"  # int(2)

    php artisan test --filter=AssertProductionConfigTest # must stay green

Then confirm scope is exactly the two intended files (the soleil engine over-reports — cross-check
against the raw diff):

    git --no-pager diff --stat
    # optionally: soleil-ai-review-engine_detect_changes({scope:"staged", repo:"soleil-hostel"})
</verification>

<output_format>
Follow CLAUDE.md output-style policy. Produce the change under
`.claude/output-styles/execution-plan.md` (the plan) and report results under
`.claude/output-styles/execution.md` (what changed). Tag every finding `[CONFIRMED]` (verified in
source/runtime), `[INFERRED]`, `[UNPROVEN]`, or `[ACTION]`; untagged claims are a defect. End with
the `git diff` and the test/tinker output as evidence.
</output_format>

<stop_conditions>
Stop and confirm with me before: editing any file other than the two named; touching any existing
symbol, the Stripe block, AssertProductionConfig, or migrations; exceeding 2 files; or committing.
Do NOT commit, push, or merge — branch `feature/momo-sandbox-payment` (create from `dev` if absent),
leave the change uncommitted, and show me the diff + verification output for review.
</stop_conditions>
````
