---
schema_version: 1.0
produced_by_batch: RC1-R3-AGP
date: 2026-03-24
governs: frontend-reviewer, security-reviewer
scope: API endpoint security ownership boundary
---

# API Endpoint Security Handoff Protocol

> Governs how `frontend-reviewer` and `security-reviewer` divide API endpoint security scope.
> Created to resolve the shared ownership of `frontend/src/shared/lib/api.ts` and API endpoint protection.

## Ownership Boundaries

| Scope | Primary Owner | Secondary / Escalation | Notes |
|-------|--------------|----------------------|-------|
| API call hygiene (`@/shared/lib/api` imports, `/v1/` prefix, no second Axios instance) | frontend-reviewer | — | Pattern compliance, not security |
| `withCredentials: true` on shared Axios instance | frontend-reviewer | security-reviewer | Frontend owns the setting; security-reviewer validates it hasn't been removed |
| CSRF interceptor (`X-XSRF-TOKEN` header injection) | security-reviewer | frontend-reviewer (detection) | Security owns the invariant; frontend-reviewer flags if interceptor code is modified |
| 401 refresh queue / token refresh behavior | security-reviewer | — | Auth flow integrity |
| Route protection (`auth` middleware on backend routes) | security-reviewer | — | Authorization coverage |
| Policy/Gate coverage on controller actions | security-reviewer | — | Authorization completeness |
| Input sanitization (`HtmlPurifierService`) | security-reviewer | — | XSS prevention |
| API response shape consistency | frontend-reviewer | — | Envelope compliance |
| Cross-feature import boundaries in API modules | frontend-reviewer | — | Architecture compliance |

## Handoff Trigger

`frontend-reviewer` **must escalate to `security-reviewer`** when any of these conditions are detected:

1. The `withCredentials` setting is modified, removed, or conditionally applied
2. The CSRF interceptor in `api.ts` is modified (any change to request interceptor touching `X-XSRF-TOKEN`)
3. The 401 response interceptor (refresh/retry logic) is modified
4. A new Axios instance is created that bypasses the shared client's security interceptors
5. API calls are made to auth endpoints (`/login`, `/logout`, `/refresh`, `/csrf-cookie`) outside the established auth flow

When **none** of these conditions are present, `frontend-reviewer` handles API-related findings independently.

## Output Contract

When `frontend-reviewer` escalates to `security-reviewer`, the escalation must include:

```
| Field | Required |
|-------|----------|
| File:Line | YES — exact location of the change |
| Change description | YES — what was modified |
| Trigger condition | YES — which of the 5 conditions above was met |
| frontend-reviewer assessment | YES — whether the change appears intentional or accidental |
| Suggested severity | OPTIONAL — frontend-reviewer's initial severity estimate |
```

`security-reviewer` then owns the final verdict (severity, suggested fix, approve/block recommendation).

## Escalation

If ownership is ambiguous (a finding touches both pattern compliance AND security invariants and neither agent has clear primary ownership):

1. `frontend-reviewer` documents the finding with its assessment
2. `security-reviewer` reviews and assigns final ownership
3. If both agents have reviewed and disagree, the finding is surfaced to the human operator with both assessments
