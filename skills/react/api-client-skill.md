# React API Client Skill

Use this skill when changing frontend API calls, auth refresh behavior, or request/response handling.

## When to Use This Skill

- You add or modify API calls in feature `*.api.ts` modules.
- You change auth, CSRF, or token-refresh behavior in the shared client.
- You see duplicated fetch/axios wrappers or inconsistent endpoint handling.

## Canonical rules

- `.agent/rules/frontend-preserve-boundaries-and-ui-standards.md`
- `.agent/rules/auth-token-safety.md`

Typed API method pattern:

```ts
export async function getRooms(): Promise<Room[]> {
  const response = await api.get<RoomsResponse>('/rooms')
  return response.data.data
}
```

## Implementation Checklist

1. Confirm endpoint contract and response shape.
   - Verify route version (`/v1`, `/v2`, legacy) before coding.
2. Add or update feature `*.api.ts` method using shared `api`.
3. Keep types close to feature (`*.types.ts`) or shared type module.
4. If auth behavior changes, update shared interceptors carefully.
   - Request interceptor: CSRF.
   - Response interceptor: refresh and retry.
5. Validate protected-route behavior after refresh failure.
6. Add/update tests for client/interceptor behavior.
7. Verify redirect behavior after refresh failure on protected routes.

## Verification / DoD

```bash
# API client tests
cd frontend && npx vitest run src/shared/lib/api.test.ts
cd frontend && npx vitest run src/features/auth/AuthContext.test.tsx
cd frontend && npx tsc --noEmit

# Baseline repo gates
cd backend && php artisan test
cd frontend && npx vitest run
docker compose config
```

## Common Failure Modes

- Creating a second API client with different interceptors.
- Forgetting `withCredentials`, breaking cookie auth.
- Breaking CSRF header injection on POST/PUT/PATCH/DELETE requests.
- Retrying 401 requests without queueing, causing refresh races.
- Returning raw Axios response objects from feature APIs instead of typed payload data.
- Hardcoding full URLs in feature APIs instead of using client base URL configuration.
- Mixing v1 and legacy auth endpoints unintentionally in the same feature flow.
- Swallowing API errors without preserving actionable response context for UI.
- Diverging response typing from backend envelope and breaking downstream consumers.

## References

- `../../AGENTS.md`
- `../../frontend/src/shared/lib/api.ts`
- `../../frontend/src/shared/lib/api.test.ts`
- `../../frontend/src/shared/utils/csrf.ts`
- `../../frontend/src/features/booking/booking.api.ts`
- `../../frontend/src/features/rooms/room.api.ts`
- `../../frontend/src/features/locations/location.api.ts`
- `../../frontend/src/features/auth/AuthContext.tsx`
