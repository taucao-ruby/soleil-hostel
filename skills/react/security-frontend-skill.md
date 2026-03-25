# React Frontend Security Skill

Use this skill for frontend auth, CSRF, sanitization, CSP-sensitive UI, and secure API interaction changes.

## When to Use This Skill

- You change auth flows in React (`AuthContext`, login/register pages, protected routes).
- You modify request handling in shared API client and CSRF utilities.
- You touch user-input rendering or sanitization.
- You edit CSP-related frontend build behavior.

## Canonical rules

- `.agent/rules/auth-token-safety.md`
- `.agent/rules/security-runtime-hygiene.md`
- `.agent/rules/frontend-preserve-boundaries-and-ui-standards.md`

Secure rendering checklist:

- Prefer plain text rendering for user-generated content.
- Escape/sanitize any value that may include markup.
- Avoid leaking backend error internals directly to end users.

## Implementation Checklist

1. Identify security-sensitive surfaces in the change.
   - Auth, CSRF, input rendering, route redirects, or token refresh.
2. Use shared API client and csrf/security helpers.
3. Keep endpoint path usage aligned with backend auth mode and versioning.
4. Validate that `withCredentials` and refresh/retry behavior still work.
5. Ensure validation and sanitization run before rendering user input.
6. Verify protected-route behavior after token expiration and refresh failure.
7. Add/update security-focused tests.
8. Confirm login, refresh, and logout flows still clear client-side auth artifacts correctly.

## Verification / DoD

```bash
# Frontend security-focused tests
cd frontend && npx vitest run src/shared/utils/security.test.ts
cd frontend && npx vitest run src/shared/utils/csrf.test.ts
cd frontend && npx vitest run src/shared/lib/api.test.ts
cd frontend && npx vitest run src/features/auth/AuthContext.test.tsx
cd frontend && npx tsc --noEmit

# Baseline repo gates
cd backend && php artisan test
docker compose config
```

## Common Failure Modes

- Introducing direct `fetch` or custom client logic that bypasses shared CSRF handling.
- Accidental token persistence in browser storage for request auth.
- Rendering unsanitized user content in UI components.
- Breaking refresh behavior and leaving users in redirect loops.
- Weakening CSP compatibility by introducing unsafe inline script assumptions.
- Showing raw server exception payloads directly in UI.
- Storing long-lived auth artifacts in non-ephemeral storage by accident.
- Missing logout cleanup on refresh failure paths.

## References

- `../../AGENTS.md`
- `../../frontend/src/shared/lib/api.ts`
- `../../frontend/src/shared/utils/csrf.ts`
- `../../frontend/src/shared/utils/security.ts`
- `../../frontend/src/features/auth/AuthContext.tsx`
- `../../frontend/src/features/auth/ProtectedRoute.tsx`
- `../../frontend/vite.config.ts`
- `../../backend/app/Http/Middleware/SecurityHeaders.php`
- `../../backend/config/sanctum.php`
