# Laravel Security and Secrets Skill

Use this skill for auth/session/cookie/security-header changes and any work touching credentials or sensitive runtime config.

## When to Use This Skill

- You modify auth middleware/controllers, token handling, or session behavior.
- You edit `.env*`, Docker env wiring, or security-related config files.
- You add logs around auth, payment, booking, or user identity flows.
- You change CORS/CSP/security headers or rate limiting/security middleware.

## Non-negotiables

- Never commit real secrets.
  - No real `APP_KEY`, Redis password, API keys, tokens, or webhook secrets.
- Keep runtime config discipline.
  - Use `config()` in app logic, not `env()` calls in controllers/services.
- Preserve cookie and token security flags.
  - `HttpOnly`, environment-aware `Secure`, strict same-site settings.
- Preserve token validity checks on protected flows.
  - Revocation and expiry checks must remain enforced.
- Avoid sensitive data leakage in logs and tests.
  - Use existing masking processors; avoid printing tokens/passwords.
- Keep CI security checks viable.
  - Gitleaks and dependency audits must remain runnable in workflows.

## Implementation Checklist

1. Identify all sensitive paths in the change.
   - Auth endpoints, middleware, session/cookie config, Docker env wiring.
2. Confirm config values are read from config files.
   - Avoid hardcoded security constants in endpoint logic.
3. Validate logging behavior.
   - Include enough context for debugging without exposing secrets.
4. Review environment variable defaults.
   - Keep dev-safe defaults; do not bake production secrets into repo files.
5. Re-check CORS and cookie auth compatibility.
   - Cross-origin credentials and CSRF protections must still align.
6. Add/update security tests when behavior changes.

## Verification / DoD

```bash
# Security-related backend checks
cd backend && php artisan test tests/Feature/Security/CsrfProtectionTest.php
cd backend && php artisan test tests/Feature/Security/SecurityHeadersTest.php
cd backend && php artisan test tests/Feature/HttpOnlyCookieAuthenticationTest.php

# Baseline repo gates
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

If available in your environment, also run:

```bash
cd backend && composer audit
cd frontend && pnpm audit --audit-level=high
```

## Common Failure Modes

- Secret values copied into committed compose/config/docs files.
- Breaking cookie auth by weakening or mismatching cookie flags.
- Logging auth artifacts (token strings, auth headers, password fields).
- Bypassing middleware-based validity checks in new auth code paths.
- Introducing runtime `env()` checks in application logic.

## References

- `../../AGENTS.md`
- `../../backend/config/sanctum.php`
- `../../backend/config/session.php`
- `../../backend/config/cors.php`
- `../../backend/config/logging.php`
- `../../backend/app/Logging/SensitiveDataProcessor.php`
- `../../backend/app/Http/Middleware/CheckTokenNotRevokedAndNotExpired.php`
- `../../backend/app/Http/Middleware/CheckHttpOnlyTokenValid.php`
- `../../backend/app/Http/Middleware/SecurityHeaders.php`
- `../../docker-compose.yml`
- `../../.github/workflows/tests.yml`
