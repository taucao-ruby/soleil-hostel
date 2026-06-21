# MoMo T7 — Execution Prompt for Opus 4.8

> Paste **everything inside the code fence below** into the executor. Self-contained, grounded in the
> current Soleil Hostel tree, scoped to **T7 only** (a surgical edit to `routes/api/v1.php`: one import +
> two routes). The security-load-bearing fact: the IPN route is PUBLIC and must carry NO auth middleware.

````text
<role>
You are a senior Laravel 12 engineer executing inside the Soleil Hostel monorepo. You make the smallest
correct edit, you place routes in the exact right middleware group, and you treat CLAUDE.md + its decision
order as binding. You verify with `route:list`, not assumptions.
</role>

<context>
You are executing task **T7** of `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` — wiring the T6
`MoMoPaymentController` into the API. T6 (controller) is done; T8 (tests) depends on these routes existing.

Authority order (higher wins): CLAUDE.md → docs/agents/CONTRACT.md → the execution plan → this prompt.
Unresolvable conflict → stop and surface as `UNRESOLVED`.

Two routes with opposite trust models in one file:
- `create` is an authenticated guest action — it belongs in the SAME `['check_token_valid', 'verified']`
  group and gets the SAME `throttle:10,1` as the existing Stripe `payment-intent` route.
- `ipn` is a PUBLIC server→server callback authenticated ONLY by the MoMo HMAC signature (verified
  fail-closed in T6). It MUST live OUTSIDE every auth group — no `check_token_valid`, no `verified`, no
  `role:*`. Putting it inside an auth group would 401 every legitimate MoMo callback.
</context>

<task>
Edit ONLY `backend/routes/api/v1.php`:
1. Add the controller import.
2. Add the authed create route inside the existing `['check_token_valid', 'verified']` group, beside the
   Stripe payment routes: `POST /bookings/{booking}/momo/create`, name `v1.bookings.momo.create`, `throttle:10,1`.
3. Add the public IPN route at top level (outside any auth group): `POST /payments/momo/ipn`, name
   `v1.payments.momo.ipn`, NO auth middleware.

Change nothing else. Do not touch the controller, other routes, or any other file. Do not reorder or
reformat existing routes.
</task>

<authoritative_references>
Inspect the live file first; these anchors are from the current tree:

1. `backend/routes/api/v1.php`:
   - Imports block (≈ line 8): `use App\Http\Controllers\Payment\BookingPaymentController;` — add the MoMo
     import alongside it (Pint `ordered_imports` sorts alphabetically: `Payment\BookingPaymentController`
     then `Payment\MoMoPaymentController`, both before `ReviewController`).
   - The authed/verified group opens at `Route::middleware(['check_token_valid', 'verified'])->group(...)`
     (≈ line 51). The Stripe payment routes are inside it (≈ lines 58–61):
       Route::post('/bookings/{booking}/payment-intent', [BookingPaymentController::class, 'createPaymentIntent'])
           ->name('v1.bookings.paymentIntent')->middleware('throttle:10,1');
     Put the MoMo `create` route immediately after the `payment/verify` route (≈ line 61), still INSIDE this group.
   - The group closes at ≈ line 104; the AI harness include is ≈ line 106
     (`Route::prefix('ai')->group(base_path('routes/api/v1_ai.php'));`). Put the PUBLIC IPN route between the
     group's closing `});` and the AI include, in its own clearly-commented public section.
   - Naming convention throughout is `v1.<area>.<action>`; throttle is applied as `->middleware('throttle:10,1')`.
2. `docs/.../MOMO_SANDBOX_EXECUTION_PLAN.md` §3 T7 (the spec + the "IPN has no check_token_valid" acceptance).
</authoritative_references>

<implementation_spec>
Edit 1 — import (let Pint settle final ordering; this position is already correct):

    use App\Http\Controllers\Payment\BookingPaymentController;
    use App\Http\Controllers\Payment\MoMoPaymentController;

Edit 2 — INSIDE the `['check_token_valid', 'verified']` group, right after the existing
`v1.bookings.payment.verify` route:

    Route::post('/bookings/{booking}/momo/create', [MoMoPaymentController::class, 'create'])
        ->name('v1.bookings.momo.create')->middleware('throttle:10,1');

Edit 3 — OUTSIDE the group (top level), before the AI harness include:

    // ========== MOMO IPN (v1) — PUBLIC server→server callback ==========
    // No auth middleware by design: the MoMo HMAC signature IS the authentication, verified fail-closed in
    // MoMoPaymentController::ipn. This route MUST NOT carry check_token_valid / verified / role:* — MoMo's
    // servers cannot present a Sanctum token. It is server→server (not a SANCTUM_STATEFUL_DOMAIN), so no
    // session/CSRF applies; the global `api` group (throttle:api, bindings) is the only middleware.
    Route::post('/payments/momo/ipn', [MoMoPaymentController::class, 'ipn'])->name('v1.payments.momo.ipn');

Use exact-anchor edits; the `{booking}` segment uses the same implicit Booking binding as the Stripe route.
Do NOT add a throttle, auth, or CSRF middleware to the IPN route (a generous webhook throttle is defensible
but is NOT in scope for T7 — leave it off unless I ask).
</implementation_spec>

<acceptance_criteria>
1. `php artisan route:list` lists both `v1.bookings.momo.create` (POST `api/v1/bookings/{booking}/momo/create`)
   and `v1.payments.momo.ipn` (POST `api/v1/payments/momo/ipn`).
2. `create` middleware = the authed/verified stack (`check_token_valid`, `verified`) + `throttle:10,1`, matching
   the Stripe payment-intent route.
3. `ipn` middleware does NOT include `check_token_valid` (nor `verified`/`role:*`) — only the global `api` group.
4. Exactly one file changed (`routes/api/v1.php`), additive only; no existing route altered; Pint clean.
</acceptance_criteria>

<verification>
Run from `backend/`:

    composer lint                                   # Pint: import order + style clean
    php artisan route:list --path=momo              # both routes appear (both paths contain "momo")
    php artisan route:list -v --name=v1.bookings.momo.create   # shows check_token_valid, verified, throttle:10,1
    php artisan route:list -v --name=v1.payments.momo.ipn      # MUST NOT show check_token_valid / verified / role
    php artisan route:list                          # sanity: no duplicate-name or load errors

    composer test                                   # full suite still green
    git --no-pager diff routes/api/v1.php           # only the import + two routes added

Eyeball the `-v` middleware column on the IPN route — the absence of `check_token_valid` is the explicit
acceptance gate (plan §3 T7).
</verification>

<output_format>
Follow CLAUDE.md output-style policy: change under `.claude/output-styles/execution-plan.md`, results under
`.claude/output-styles/execution.md`. Tag findings `[CONFIRMED]`, `[INFERRED]`, `[UNPROVEN]`, `[ACTION]`.
End with the `git diff` of `v1.php` plus the two `route:list -v` outputs (proving the IPN route's middleware
stack omits `check_token_valid`) as evidence.
</output_format>

<stop_conditions>
Stop and confirm with me before: editing any file other than `routes/api/v1.php`; placing the IPN route inside
any auth group or adding auth/CSRF/`check_token_valid` to it; changing the controller or an existing route;
or committing. Do NOT commit, push, or merge — continue branch `feature/momo-sandbox-payment`, leave the change
uncommitted, and show me the diff + route:list verification.
</stop_conditions>
````
