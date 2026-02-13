# React Performance and Core Web Vitals Skill

Use this skill when changing route loading, bundle strategy, rendering behavior, or frontend performance telemetry.

## When to Use This Skill

- You modify app routing, lazy loading, or suspense boundaries.
- You add heavy UI paths that can impact LCP/INP/CLS/FCP/TTFB.
- You touch build output strategy in `vite.config.ts`.
- You change Web Vitals instrumentation.

## Non-negotiables

- Keep Web Vitals initialization behavior.
  - `initWebVitals()` should remain production-gated in `main.tsx`.
- Preserve route-level code splitting.
  - Continue using `React.lazy` and `Suspense` in router.
- Keep build optimization defaults.
  - Existing manual chunking in Vite should remain intentional.
- Prefer lazy loading for non-critical media and below-fold content.
  - Existing room/location cards already use `loading="lazy"` image patterns.
- Treat memoization as targeted optimization.
  - Use `useMemo`/`useCallback` where measured or clearly needed.

Current metric utility tracks:

- CLS
- INP
- FCP
- LCP
- TTFB

Use `getPerformanceRating(...)` from `webVitals.ts` if adding UI/reporting logic.

## Implementation Checklist

1. Identify potential impact to Core Web Vitals metrics.
2. Keep or improve lazy loading boundaries for route-level chunks.
3. Avoid introducing render-blocking dependencies in initial route.
4. Preserve Web Vitals telemetry wiring and metric thresholds.
5. For image-heavy components, keep lazy loading and sensible dimensions.
6. Rebuild and inspect output/perf notes after changes.
7. Compare behavior against documented baseline targets for major UI changes.

## Verification / DoD

```bash
# Frontend build and quality checks
cd frontend && npm run build
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run

# Baseline repo gates
cd backend && php artisan test
docker compose config
```

Optional baseline references:

- Review `docs/PERFORMANCE_BASELINE.md`.
- Review `frontend/src/utils/webVitals.ts` thresholds and reporting behavior.

## Common Failure Modes

- Removing lazy loading and bloating initial bundle size.
- Running Web Vitals code in non-prod contexts unintentionally.
- Adding expensive computations in render paths without memoization.
- Breaking suspense fallback behavior on protected routes.
- Ignoring performance regressions after route/component additions.

## References

- `../../AGENTS.md`
- `../../frontend/src/main.tsx`
- `../../frontend/src/app/router.tsx`
- `../../frontend/src/utils/webVitals.ts`
- `../../frontend/vite.config.ts`
- `../../frontend/src/features/rooms/RoomList.tsx`
- `../../frontend/src/features/locations/LocationCard.tsx`
- `../../docs/PERFORMANCE_BASELINE.md`
