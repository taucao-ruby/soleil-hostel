# CI Fix — MoMo IPN signature verify (valid IPN → 400) — Execution Prompt for Opus 4.8

> Paste **everything inside the code fence below** into the executor. CI/test failure → use the **RCA output
> style** (`.claude/output-styles/rca.md`). This is a real production-code defect in `MoMoService` that the T8
> feature tests correctly caught. Confirm the root cause before editing, then make the minimal fix.

````text
<role>
You are a senior Laravel / PHP payments-security engineer executing inside the Soleil Hostel monorepo. You run
a disciplined RCA — reproduce, instrument, isolate, fix, prove — and you treat CLAUDE.md + its decision order as
binding. You fix the signing defect at its root, not by loosening the verifier.
</role>

<context>
`php artisan test` fails with 4 failures, all in `tests/Feature/Payment/MoMoIpnTest.php`:
  - test_valid_ipn_confirms_a_pending_prepaid_booking      (expected 204, got 400)
  - test_replayed_ipn_acks_204_and_confirms_exactly_once   (expected 204, got 400)
  - test_amount_mismatch_is_recorded_failed_and_does_not_confirm (expected 204, got 400)
  - test_ipn_for_unknown_order_is_handled_without_crash     (expected 204, got 400)
The bad-signature test (expects 400) PASSES, and `tests/Unit/MoMoServiceTest.php` (signature vector) PASSES.

Authority order (higher wins): CLAUDE.md → docs/agents/CONTRACT.md → docs/HOOKS.md → this prompt. Unresolvable
conflict → `UNRESOLVED`. This is the MoMo sandbox feature (branch `feature/momo-sandbox-payment`).
</context>

<root_cause>
`[INFERRED, high-confidence — confirm in step 1]` `MoMoService::signIpn()` builds its HMAC base from the INPUT
array's keys (e.g. `ksort()` / iterate-the-array) instead of a FIXED canonical field list. The failure signature
proves it:
  - The unit vector test passes because its input is the clean 13-field set whose alphabetical `ksort` order
    happens to equal MoMo's §2 canonical order (the §2 string IS alphabetical) — so a sorted-keys impl looks correct.
  - Every authentic IPN fails because the controller calls `verifyIpnSignature($payload)` on the DECODED request
    body, which now contains the `signature` field (and possibly extra MoMo fields). A sorted/iterated base folds
    `signature=...` into the signing string, so the recomputed HMAC never equals the provided one → `verifyIpnSignature`
    returns false → the `ipn` fail-closed ladder returns 400 for EVERY valid IPN.
  - The bad-signature test still 400s (genuinely wrong), masking the regression on the happy path.
This is the T3 security rule S1 ("build the raw string from an explicit hardcoded field order; never derive order
from attacker-controlled array keys; never include a field outside MoMo's canonical list") being violated. The T8
feature test did its job and surfaced a real production defect — fix the production code, not the test.
</root_cause>

<task>
1. RCA-confirm the cause (instrument the verifier; see the polluted base).
2. Fix `MoMoService::signIpn()` AND `signCreatePayment()` to build the base from the explicit §2 canonical field
   list, reading ONLY those keys and ignoring `signature` + any non-canonical field.
3. Harden the unit test so this exact regression can never return.
4. Re-run the 4 cases + the unit test + the full suite; all green.
5. Commit on the feature branch. No push, no merge. Report and wait.
</task>

<diagnostic_steps>
Confirm before editing (do not blind-fix):

    # Reproduce just the failing cases
    php artisan test --filter=MoMoIpnTest

    # Instrument: temporarily dump the exact string verifyIpnSignature signs, plus the provided signature, then
    # run ONE failing case. You will see the base contains `signature=...` (and/or extra keys) — that is the bug.
    #   in MoMoService::verifyIpnSignature (TEMP): \Log::debug('momo_ipn_base', ['base'=>$rawBeingSigned]);
    php artisan test --filter=test_valid_ipn_confirms_a_pending_prepaid_booking
    #   inspect storage/logs — confirm the base differs from §2 (extra `signature` field). REMOVE the temp log after.

    # Inspect the implementation directly:
    sed -n '1,200p' app/Services/MoMoService.php   # look at signIpn / signCreatePayment / verifyIpnSignature
</diagnostic_steps>

<fix_spec>
Rebuild both signers from the EXACT §2 canonical orderings, via explicit `sprintf`/concatenation over a hardcoded
key list — NOT `ksort`, NOT iterating `$fields`. Coerce each value `(string) ($fields[$k] ?? '')`. Include no key
outside the list (so `signature` and any unknown MoMo field are structurally impossible to fold in).

IPN base (§2):
    accessKey={accessKey}&amount={amount}&extraData={extraData}&message={message}&orderId={orderId}&orderInfo={orderInfo}&orderType={orderType}&partnerCode={partnerCode}&payType={payType}&requestId={requestId}&responseTime={responseTime}&resultCode={resultCode}&transId={transId}

createPayment base (§2):
    accessKey={accessKey}&amount={amount}&extraData={extraData}&ipnUrl={ipnUrl}&orderId={orderId}&orderInfo={orderInfo}&partnerCode={partnerCode}&redirectUrl={redirectUrl}&requestId={requestId}&requestType={requestType}

`verifyIpnSignature` stays constant-time (`hash_equals`) and fail-closed (blank secret → false); after the fix it
recomputes the base over the canonical fields only, so the inbound `signature` field no longer corrupts it.

`[UNPROVEN — secondary, verify for the live demo; does NOT affect these tests]` MoMo's IPN payload does NOT carry
`accessKey`, yet the IPN base needs it. In the tests both sides resolve `accessKey` to '' (unset in `testing`), so
they match and the suite passes either way. For REAL sandbox traffic, MoMo signs the IPN with the partner access
key, so `verifyIpnSignature` must source `accessKey` from `config('services.momo.access_key')` when building the
base (inject it before signing) — otherwise live IPNs will 400. Handle this now (inject config accessKey in the
IPN verify path) and add a note to validate against the live sandbox. Keep `signIpn` itself a pure function of its
input array so the unit vector test stays deterministic.
</fix_spec>

<impact_analysis>
`signIpn`/`signCreatePayment`/`verifyIpnSignature` are existing symbols — CLAUDE.md requires impact analysis BEFORE
editing. Run and report the blast radius first:
    soleil-ai-review-engine_impact({target: "verifyIpnSignature", direction: "upstream"})
    soleil-ai-review-engine_impact({target: "signIpn", direction: "upstream"})
Expected upstream: `MoMoPaymentController::ipn`, `MoMoService::createPayment`, and the MoMo tests. If impact comes
back HIGH/CRITICAL beyond that set, STOP and report before editing.
</impact_analysis>

<regression_guard>
Harden `tests/Unit/MoMoServiceTest.php` so the exact defect cannot return:
    public function test_sign_ipn_ignores_signature_and_unknown_fields(): void {
        config(['services.momo.secret_key' => 'K951B6PE1waDMi640xX08PD3vg6EkVlz']);
        $svc = app(\App\Services\MoMoService::class);
        $canonical = [/* the 13 §2 IPN fields with fixed values */];
        $polluted  = $canonical + ['signature' => 'deadbeef', 'extra' => 'junk', 'lang' => 'vi'];
        $this->assertSame($svc->signIpn($canonical), $svc->signIpn($polluted));   // extras must not change the base
    }
(Optionally add the equivalent for `signCreatePayment`.) This is the assertion the original unit test was missing —
it directly encodes the root cause.
</regression_guard>

<verification>
    composer lint
    php artisan test --filter=MoMoServiceTest          # unit incl. the new guard — green
    php artisan test --filter=MoMoIpnTest              # all 5 feature cases — green (4 previously-red now 204)
    docker compose up -d db && php scripts/check-test-db.php   # if not already up
    composer test                                      # FULL suite green; PHPUnit deprecation count unchanged
    git --no-pager diff --stat                         # only MoMoService.php + MoMoServiceTest.php

Confirm the 4 previously-failing assertions now get 204 and the bad-signature case still 400 (verifier still rejects
a genuinely forged signature — you fixed the base, you did NOT weaken verification).
</verification>

<commit_spec>
- Branch: `feature/momo-sandbox-payment` (this fixes T3 within the feature).
- Stage ONLY `backend/app/Services/MoMoService.php` and `backend/tests/Unit/MoMoServiceTest.php` (explicit `git add`,
  never `-A`; do not stage `docs/backend/*_PROMPT.md`). If the MoMo feature was already committed (T9), make a focused
  follow-up commit; if it is still uncommitted, fold this into the feature commit.
- Message: `fix(backend): build MoMo signature base from canonical fields, ignore non-canonical input`
  NO `Co-Authored-By`/attribution; NO `--no-verify`/`SKIP_HOOKS`.
- After commit: soleil reindex per CLAUDE.md (auto-hook, or `npx soleil-engine-cli analyze [--embeddings]`).
</commit_spec>

<output_format>
Use the RCA output style `.claude/output-styles/rca.md`. Tag findings `[CONFIRMED]`, `[INFERRED]`, `[UNPROVEN]`
(esp. the accessKey-sourcing item), `[ACTION]`. End with: the instrumented base showing the pre-fix pollution, the
before/after `php artisan test --filter=MoMoIpnTest` results, and `git diff --stat`.
</output_format>

<stop_conditions>
Stop and confirm with me before: "fixing" this by stripping `signature` inside the verifier while leaving the
sorted/iterated base (treat the canonical-list rewrite as the real fix); editing the controller/handler/tests' EXPECTED
status codes to make them pass; weakening `hash_equals`/fail-closed; editing any file beyond `MoMoService.php` +
the unit test; proceeding if soleil impact returns HIGH/CRITICAL outside the expected callers; pushing; or merging.
Do NOT commit until `MoMoIpnTest` + the full suite are green. Show me the RCA + diff + test output and wait for go.
</stop_conditions>
````
