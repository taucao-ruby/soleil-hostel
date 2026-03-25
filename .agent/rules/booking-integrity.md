---
verified-against: docs/agents/ARCHITECTURE_FACTS.md
section: "Booking Domain — Overlap Prevention, Concurrency Control, Booking Status"
last-verified: 2026-03-17
maintained-by: docs-sync
---

# Booking Integrity

## Purpose
Prevent double-bookings and preserve the booking-domain relationships that availability, reviews, and denormalized location history depend on.

## Rule
- Booking availability remains half-open `[check_in, check_out)`; adjacent stays are valid and overlap logic may only treat `pending` and `confirmed` as blocking states.
- `deleted_at IS NULL` remains aligned in both app-layer overlap checks and the PostgreSQL exclusion constraint.
- Booking conflict checks and booking writes stay inside the same transaction/lock boundary on write paths that can introduce overlap or race conditions.
- `bookings.location_id` remains trigger-managed denormalization from `rooms.location_id`; do not replace that contract with application-side drift.
- Reviews remain one-to-one with bookings through `reviews.booking_id`; do not weaken or remove that integrity contract.

## Why it exists
These constraints stop false availability, real double-bookings, analytics drift across locations, and orphaned review data.

## Applies to
Agents, humans, skills, commands, reviews, migrations, and tests touching bookings, availability, cancellations, reviews, or booking-related schema.

## Violations
- Widening overlap checks to cancelled or refund states.
- Removing `deleted_at IS NULL` from either the query path or the DB constraint.
- Running overlap detection outside the write transaction or without the established lock path.
- Setting `bookings.location_id` directly in app code.
- Breaking the `reviews.booking_id` one-review-per-booking relationship.

## Enforcement
- Canonical sources: `docs/agents/ARCHITECTURE_FACTS.md` § "Booking Domain" and `docs/DB_FACTS.md`.
- Database enforcement: `no_overlapping_bookings`, related CHECK/FK constraints, and the booking-location trigger.
- Review and validation: `tests/Feature/CreateBookingConcurrencyTest.php`, `tests/Feature/Booking/ConcurrentBookingTest.php`, `tests/Feature/Booking/BookingSoftDeleteTest.php`, `tests/Feature/Database/TransactionIsolationIntegrationTest.php`, `tests/Feature/Database/FkDeletePolicyTest.php`, `.agent/scripts/check-locking-coverage.sh`, `.claude/commands/audit-security.md`, `.claude/commands/review-pr.md`.

## Linked skills / hooks
- `skills/laravel/booking-overlap-skill.md`
- `skills/laravel/transactions-locking-skill.md`
- `skills/react/forms-validation-skill.md`
