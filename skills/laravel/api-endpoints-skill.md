# Laravel API Endpoints Skill

Use this skill to add or modify Laravel API endpoints while keeping Soleil Hostel architecture and response behavior consistent.

## When to Use This Skill

- You add or change API routes under `backend/routes/api*.php`.
- You create or update controller actions in `backend/app/Http/Controllers/`.
- You introduce new request validation, authorization, or API resource serialization.
- You touch endpoint behavior that can break response format, authZ, or query performance.

## Non-negotiables

- Keep controllers thin.
  - Input validation in `FormRequest` classes (`backend/app/Http/Requests/*Request.php`).
  - Business logic in services (`backend/app/Services/*Service.php`).
  - Data access in repositories behind contracts (`backend/app/Repositories/Contracts/*Interface.php`).
- Keep response envelope consistent.
  - Existing helper trait: `App\Traits\ApiResponse`.
  - Standard response wrapper class: `App\Http\Responses\ApiResponse`.
  - Global API exception rendering is centralized in `backend/bootstrap/app.php`.
- Enforce authorization through policies, gates, or middleware.
  - Use `$this->authorize(...)`, policy methods, and `role` middleware aliases.
  - Do not scatter ad hoc role checks in controllers.
- Prevent N+1 issues.
  - Use eager loading (`with`, `withCount`) and model scopes like `withCommonRelations`.
- Use `config()` in runtime logic, not `env()`.

## Implementation Checklist

1. Identify endpoint version and route placement.
   - Prefer existing versioned routes (`/api/v1`, `/api/v2`) or existing legacy strategy.
2. Add or update the `FormRequest`.
   - Keep field names aligned with frontend payload keys.
   - Add custom messages only where they improve API clarity.
3. Add controller action with minimal orchestration.
   - Validate input via typed `FormRequest`.
   - Authorize via policy/gate/middleware.
   - Delegate logic to service layer.
4. Return stable response shape.
   - Use API resources for domain payloads where applicable.
   - Keep `success`, `message`, `data`, and error behavior predictable.
5. Check query behavior and loading strategy.
   - Add eager loading and selected columns to avoid endpoint regressions.
6. Add or update tests.
   - Feature tests for success and failure paths.
   - Authorization tests.
   - Validation tests and N+1-sensitive tests where relevant.

## Verification / DoD

```bash
# Endpoint-specific (pick what applies)
cd backend && php artisan test tests/Feature/Validation/ApiValidationTest.php
cd backend && php artisan test tests/Feature/NPlusOneQueriesTest.php

# Baseline repo gates
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

## Common Failure Modes

- Fat controller: validation, business rules, and DB queries mixed into one action.
- Inconsistent API envelopes between similar endpoints.
- Missing policy checks leading to unauthorized access.
- N+1 query regressions after adding relations to responses.
- Route version mismatch (changing legacy route behavior unintentionally).
- Runtime `env()` usage in endpoint/service code.

## References

- `../../AGENTS.md`
- `../../backend/routes/api.php`
- `../../backend/routes/api/v1.php`
- `../../backend/routes/api/v2.php`
- `../../backend/app/Http/Controllers/RoomController.php`
- `../../backend/app/Http/Controllers/BookingController.php`
- `../../backend/app/Http/Requests/RoomRequest.php`
- `../../backend/app/Http/Requests/StoreBookingRequest.php`
- `../../backend/app/Traits/ApiResponse.php`
- `../../backend/app/Http/Responses/ApiResponse.php`
- `../../backend/bootstrap/app.php`
- `../../backend/app/Providers/AuthServiceProvider.php`
- `../../backend/tests/Feature/NPlusOneQueriesTest.php`
