# CI Fix (round 2) — MoMo IPN still 400 on valid signature — Execution Prompt for Opus 4.8

> Paste **everything inside the code fence below** into the executor. The previous fix did NOT resolve it —
> the same 4 cases still fail `204 → 400`. Use the **RCA output style** (`.claude/output-styles/rca.md`) and be
> strictly EVIDENCE-FIRST: capture the proof of WHICH branch 400s and WHY before touching any production code.
> No more hypothesis-driven edits.

````text
<role>
You are a senior Laravel / PHP payments-security engineer running a disciplined, evidence-first RCA inside the
Soleil Hostel monorepo. A prior fix attempt failed, so you will NOT change production code until you have
captured concrete proof of the failing branch and the exact byte-level cause. You treat CLAUDE.md + its
decision order as binding, and you never weaken signature verification to make a test pass.
</role>

<context>
After the previous fix, `php artisan test` STILL fails the same 4 cases in `tests/Feature/Payment/MoMoIpnTest.php`,
all `expected 204, received 400`:
  - test_valid_ipn_confirms_a_pending_prepaid_booking (L50)
  - test_replayed_ipn_acks_204_and_confirms_exactly_once (L66)
  - test_amount_mismatch_is_recorded_failed_and_does_not_confirm (L95)
  - test_ipn_for_unknown_order_is_handled_without_crash (L110)
The bad-signature test (expects 400) PASSES; the unit `MoMoServiceTest` PASSES.

New evidence from the coverage report: `App\Services\Payment\MoMoIpnHandler` is at **3.12% line coverage (1/32)**
— the handler is essentially NEVER entered. So `MoMoPaymentController::ipn` returns **400 BEFORE delegating to the
handler**, i.e. at one of its two early guards:
  - F2: decoded body is not an array (malformed/empty/wrong content-type)  → 400
  - F3: `verifyIpnSignature($payload)` returned false                      → 400
Your first job is to determine WHICH, with proof. Do not assume it is the signature again.

Authority order (higher wins): CLAUDE.md → docs/agents/CONTRACT.md → docs/HOOKS.md → this prompt. Branch:
`feature/momo-sandbox-payment`.
</context>

<evidence_first_mandate>
Do NOT edit any production file until you have pasted, in your RCA, ALL of the following captured artifacts.
This bisects the failure in one pass and ends the guessing:

STEP A — Read the three actors and compare the SIGN path vs the VERIFY path side by side:
  - `tests/Feature/Payment/MoMoIpnTest.php` — how does the helper build + sign + POST? `post()` or `postJson()`?
    Does it set `accessKey`? What exact keys/types go into the signed array?
  - `app/Services/MoMoService.php` — `signIpn()` and `verifyIpnSignature()`: where does EACH field of the base
    come from (payload vs `config`), what config KEY names are read, and does verify recompute over the same
    inputs the signer used?
  - `app/Http/Controllers/Payment/MoMoPaymentController.php` — `ipn()`: how is the body decoded (`getContent()`
    vs `$request->all()`), and which guard returns the 400?

STEP B — Isolate sign↔verify from HTTP (the decisive bisection). In `tinker` (or a throwaway script), in ONE
process, reproduce the test's signing exactly, then verify it directly — no HTTP:
    config(['services.momo.secret_key' => 'test-momo-secret']);   // mirror the test setUp EXACTLY
    $svc = app(App\Services\MoMoService::class);
    $p = [ /* the SAME IPN fields the test helper builds, same types */ ];
    $p['signature'] = $svc->signIpn($p);
    var_dump($svc->verifyIpnSignature($p));   // TRUE or FALSE?
  - If FALSE → the sign↔verify CONTRACT is broken (base or secret asymmetry). Go to STEP C.
  - If TRUE → the service is fine; the 400 is in the HTTP layer (F2 / body decode / content-type). Go to STEP D.

STEP C — When round-trip is FALSE, dump BOTH base strings and diff them byte-for-byte (temporarily expose them,
e.g. add a private `ipnSigningBase()` or log inside signIpn/verify):
    echo $baseUsedBySign,   PHP_EOL;
    echo $baseUsedByVerify, PHP_EOL;
    echo 'secret_len=', strlen((string) config('services.momo.secret_key')), PHP_EOL;
  The first character that differs IS the bug. Report the diff verbatim (redact the secret value; show its length).

STEP D — When round-trip is TRUE, instrument `ipn()` to log which guard fires and the decoded body shape:
    \Log::debug('momo_ipn_probe', ['is_array'=>is_array($payload), 'keys'=>array_keys((array)$payload),
                                    'raw_len'=>strlen($request->getContent())]);
  Run `php artisan test --filter=test_valid_ipn_confirms_a_pending_prepaid_booking`, read `storage/logs`, report.

REMOVE all temporary instrumentation before committing.
</evidence_first_mandate>

<ranked_hypotheses>
Use these as a checklist while reading STEP A — but confirm with STEP B/C/D evidence before fixing:

H1 (most likely, F3) — **accessKey asymmetry.** The IPN signing base (§2) includes `accessKey`, but a real MoMo IPN
payload does NOT carry it. If `signIpn` (used by the test) reads `accessKey` from the array (→ '') while
`verifyIpnSignature` injects it from `config('services.momo.access_key')` (or vice-versa), the two bases differ.
FIX: ONE source of truth — both sign and verify must take `accessKey` from `config('services.momo.access_key')`
(never from the inbound payload), so they are symmetric. Then the test helper (which omits accessKey) and the
controller agree because both read the same config.

H2 (F3) — **config key-name / secret mismatch.** `signIpn` and `verifyIpnSignature` read different config keys (e.g.
`services.momo.secret_key` vs `services.momo.secret`), or the service cached an empty secret at construction before
the test's `config([...])` ran. STEP C's `secret_len` and the base diff expose this.

H3 (F2) — **`post()` vs `postJson()` / body decode.** If the test uses `post()` (form-encoded) or the controller
reads `json_decode($request->getContent())` against a non-JSON body, the decoded payload is null/array-shaped wrong
→ F2 returns 400 for ALL cases (the bad-sig test passes only because it also expects 400). STEP D confirms; FIX by
aligning the test to `postJson` AND/OR making the controller robust to `$request->json()->all()`.

H4 (F3) — **previous canonical-list fix not actually applied to the IPN path**, or `verify` still feeds the
signature-bearing payload into a signer that folds `signature`/extra keys in. STEP C's diff shows a stray `signature=`
or extra field.

H5 (F3) — **type/encoding drift** of a numeric field (amount/resultCode/transId/responseTime) between the in-memory
signed array and the JSON-decoded one. STEP C's diff shows it (e.g. `amount=50000` vs `amount=50000.0`).
</ranked_hypotheses>

<fix_rules>
- Fix the ONE asymmetry the evidence shows. Do NOT shotgun multiple changes.
- Single source of truth for every signing input: a field that is NOT in MoMo's inbound IPN payload (notably
  `accessKey`) MUST be sourced from `config()` in BOTH the signer and the verifier — identically. A field that IS in
  the payload must be read from the payload in both. Sign and verify must be provably symmetric.
- Keep `verifyIpnSignature` constant-time (`hash_equals`) and fail-closed (blank secret → false). Do NOT relax it,
  do NOT strip-and-retry, do NOT compare normalized/sorted variants to force a match.
- Do NOT edit the tests' EXPECTED status codes. The 4 cases asserting 204 and the bad-sig case asserting 400 are the
  contract; make production satisfy them. (You MAY fix a test-helper DEFECT — e.g. `post`→`postJson`, or the helper
  signing with an accessKey the controller doesn't — but call it out explicitly and justify it from STEP A evidence.)
</fix_rules>

<regression_guard>
Add the cheap test that was missing and would have caught this immediately — a DIRECT sign↔verify round-trip,
independent of HTTP, in `tests/Unit/MoMoServiceTest.php`:
    public function test_verify_ipn_accepts_a_payload_signed_by_sign_ipn(): void {
        config(['services.momo.secret_key' => 'test-momo-secret', 'services.momo.access_key' => 'F8BBA842ECF85']);
        $svc = app(\App\Services\MoMoService::class);
        $payload = [ /* the 12 inbound IPN fields MoMo actually sends — NO accessKey */ ];
        $payload['signature'] = $svc->signIpn($payload);
        $this->assertTrue($svc->verifyIpnSignature($payload));
    }
Also assert the negative: tampering any one field (e.g. amount) flips it to false. This pins the sign/verify
contract at the unit level so a future asymmetry fails fast and locally, not three HTTP layers away.
</regression_guard>

<impact_analysis>
You will edit existing symbols (`signIpn`/`verifyIpnSignature`, possibly `ipn`). Per CLAUDE.md, run impact FIRST and
report the blast radius:
    soleil-ai-review-engine_impact({target: "verifyIpnSignature", direction: "upstream"})
Expected upstream: `MoMoPaymentController::ipn`, the MoMo tests. If HIGH/CRITICAL beyond that, STOP and report.
</impact_analysis>

<verification>
    composer lint
    php artisan test --filter=MoMoServiceTest      # unit incl. the new round-trip guard — green
    php artisan test --filter=MoMoIpnTest          # all 5 cases green: 4 now 204, bad-sig still 400
    docker compose up -d db && php scripts/check-test-db.php   # if not already up
    composer test                                  # FULL suite green
    git --no-pager diff --stat                     # scope: MoMoService.php (+ controller iff F2) + MoMoServiceTest.php
Confirm `MoMoIpnHandler` coverage rises well above 3% (the handler is now actually reached on the happy path).
</verification>

<commit_spec>
- Branch `feature/momo-sandbox-payment`. Stage only the files the fix touched (explicit `git add`, never `-A`;
  exclude `docs/backend/*_PROMPT.md`).
- Message: `fix(backend): make MoMo IPN sign/verify symmetric so valid IPNs are accepted`
  NO `Co-Authored-By`/attribution; NO `--no-verify`/`SKIP_HOOKS`. Soleil reindex after commit per CLAUDE.md.
</commit_spec>

<output_format>
RCA output style `.claude/output-styles/rca.md`. Tag findings `[CONFIRMED]`/`[INFERRED]`/`[UNPROVEN]`/`[ACTION]`.
Your RCA MUST include, as evidence: (1) the STEP B round-trip result (TRUE/FALSE); (2) if FALSE, the two base
strings with the differing region highlighted + `secret_len`; if TRUE, the STEP D probe showing the F2 cause;
(3) the one-line root cause; (4) before/after `--filter=MoMoIpnTest`; (5) `git diff --stat`.
</output_format>

<stop_conditions>
Stop and confirm with me before: editing ANY production file before pasting STEP B (and STEP C or D) evidence;
weakening `hash_equals`/fail-closed or normalizing signatures to force a match; changing the tests' expected status
codes; editing files beyond `MoMoService.php` (+ controller only if STEP D proves an F2/body-decode bug) and the unit
test; proceeding if soleil impact is HIGH/CRITICAL outside the expected callers; pushing; or merging. If STEP B
returns TRUE and STEP D shows the test itself is malformed (e.g. `post` not `postJson`), report that as the root
cause and fix the test — but say so explicitly with the evidence.
</stop_conditions>
````
