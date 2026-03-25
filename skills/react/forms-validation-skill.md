# React Forms and Validation Skill

Use this skill when editing frontend forms, client validation logic, or API payload preparation.

## When to Use This Skill

- You change booking, login, registration, or contact form fields.
- You modify validation helpers under feature folders.
- You update error messaging or form submission behavior.
- You need frontend validation to stay aligned with Laravel `FormRequest` rules.

## Canonical rules

- `.agent/rules/backend-preserve-rbac-source-and-request-validation.md`
- `.agent/rules/frontend-preserve-boundaries-and-ui-standards.md`
- `.agent/rules/booking-integrity.md`

Validation ownership split:

- Frontend: immediate UX feedback and field guidance.
- Backend: authoritative enforcement and final rejection semantics.

## Implementation Checklist

1. Identify fields and backend request rules impacted.
   - Check relevant `FormRequest` classes in backend.
2. Update feature validation module.
   - Keep logic small, pure, and testable.
3. Wire validation into form component.
   - Prevent submit on invalid data.
   - Clear field errors on user correction.
4. Validate date constraints with explicit comparisons.
5. Preserve accessibility on errors (`aria-invalid`, `aria-describedby`).
6. Add/update tests for:
   - Valid path.
   - Each rule violation.
   - Date edge cases and payload shaping.
7. Re-check backend parity whenever field names or payload keys change.

## Verification / DoD

```bash
# Frontend validation tests
cd frontend && npx vitest run src/features/booking/booking.validation.test.ts
cd frontend && npx vitest run src/features/auth/LoginPage.test.tsx src/features/auth/RegisterPage.test.tsx
cd frontend && npx tsc --noEmit

# Backend parity checks when rules changed
cd backend && php artisan test tests/Feature/Validation/ApiValidationTest.php

# Baseline repo gates
cd backend && php artisan test
cd frontend && npx vitest run
docker compose config
```

## Common Failure Modes

- Frontend allows payloads that backend rejects (or vice versa).
- Date logic drift causing booking UX conflicts with backend rules.
- Rule changes without updating validation tests.
- Generic error handling that hides field-specific failures.
- Adding fields in UI without confirming backend request schema support.

## References

- `../../AGENTS.md`
- `../../frontend/src/features/booking/booking.validation.ts`
- `../../frontend/src/features/booking/booking.validation.test.ts`
- `../../frontend/src/features/booking/BookingForm.tsx`
- `../../frontend/src/features/auth/LoginPage.tsx`
- `../../frontend/src/features/auth/RegisterPage.tsx`
- `../../frontend/src/shared/utils/security.ts`
- `../../backend/app/Http/Requests/StoreBookingRequest.php`
- `../../backend/app/Http/Requests/UpdateBookingRequest.php`
- `../../backend/app/Http/Requests/LoginRequest.php`
