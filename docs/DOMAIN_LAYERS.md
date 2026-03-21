# Four-Layer Domain Model

Added: 2026-03-20. Describes the operational domain tables introduced in migration batch `2026_03_20_*`.

## Layer Overview

| Table | Domain | Purpose |
|-------|--------|---------|
| `bookings` | Commercial | Reservation state machine (pending → confirmed → cancelled/refunded) |
| `stays` | Operational | Occupancy lifecycle per booking (expected → in_house → checked_out) |
| `room_assignments` | Allocation | Actual room assignment history and reassignment audit trail |
| `service_recovery_cases` | Incident | Service failure incidents and compensation audit trail |

```
bookings.status         = Commercial reservation state only
stays.stay_status       = Operational stay / occupancy lifecycle
room_assignments        = Actual room allocation history and reassignment audit trail
service_recovery_cases  = Incident and compensation audit trail

Active in-house guest is derived from stays.stay_status IN ('in_house', 'late_checkout').
A static users.active flag is NOT the source of truth for in-house guest status.

Booking date overlap logic uses half-open intervals [check_in, check_out).
This logic is unchanged and must not be modified.
```

## Layer 1: bookings — Commercial Reservation State

`bookings.status` values: `pending`, `confirmed`, `refund_pending`, `cancelled`, `refund_failed`.

This is purely the commercial/payment lifecycle. A booking being `confirmed` says nothing about
whether the guest has physically arrived.

Source: `App\Enums\BookingStatus`, migration `2026_03_17_000003`.

**Invariants preserved:**
- Half-open interval `[check_in, check_out)` for overlap detection.
- Overlap statuses: `pending`, `confirmed` only.
- PostgreSQL EXCLUDE USING gist filters `deleted_at IS NULL`.
- `lockForUpdate()` required for booking creation/cancellation.

## Layer 2: stays — Operational Occupancy Lifecycle

`stays.stay_status` values: `expected`, `in_house`, `late_checkout`, `checked_out`, `no_show`, `relocated_internal`, `relocated_external`.

One stay per booking (UNIQUE `booking_id`). A stay record may be created on the day of arrival
or pre-populated at confirmation time.

Source: `App\Enums\StayStatus`, `App\Models\Stay`, migration `2026_03_20_000001`.

**Key rule:** In-house guest detection uses `stays.stay_status IN ('in_house', 'late_checkout')`.
Do NOT use a static flag on the `users` table for this purpose.

## Layer 3: room_assignments — Allocation History

`room_assignments` tracks which physical room a guest was assigned to during each window of their stay.

- `assigned_from` / `assigned_until`: the assignment window (NULL `assigned_until` = currently active).
- `assignment_type`: why the assignment was made (`original`, `equivalent_swap`, `complimentary_upgrade`, `maintenance_move`, `overflow_relocation`).
- **Partial unique index** (PostgreSQL): `UNIQUE (stay_id) WHERE assigned_until IS NULL` enforces at most one active assignment per stay at any time.

Source: `App\Enums\AssignmentType`, `App\Enums\AssignmentStatus`, `App\Models\RoomAssignment`, migration `2026_03_20_000002`.

## Layer 4: service_recovery_cases — Incident and Compensation Audit

`service_recovery_cases` records incidents where service standards were not met and tracks
the resolution and compensation actions.

- `stay_id` is nullable: an incident may be recorded before a stay exists.
- `incident_type`: `late_checkout_blocking_arrival`, `room_unavailable_maintenance`, `overbooking_no_room`, `internal_relocation`, `external_relocation`.
- `severity`: `low`, `medium`, `high`, `critical`.
- `case_status`: `open`, `investigating`, `action_in_progress`, `compensated`, `resolved`, `closed`.
- `compensation_type`: `none`, `refund_partial`, `refund_full`, `voucher`, `complimentary_upgrade`, `refund_plus_voucher`.
- All monetary amounts (`refund_amount`, `voucher_amount`, `cost_delta_absorbed`) are stored in **cents** (BIGINT), consistent with `bookings.amount`.

Source: `App\Enums\{IncidentType, IncidentSeverity, CaseStatus, CompensationType}`, `App\Models\ServiceRecoveryCase`, migration `2026_03_20_000003`.

## Entity Relationships

```
bookings (1) ──── (0..1) stays
stays    (1) ──── (0..*) room_assignments
stays    (1) ──── (0..*) service_recovery_cases
bookings (1) ──── (0..*) service_recovery_cases   [direct link, stay_id nullable]
```

## Stay Creation: Two-Path Strategy

**Future confirmed bookings (post-deployment):**
Stay rows are created lazily at booking confirmation time via `BookingService::confirmBooking()`.
`scheduled_check_in_at` and `scheduled_check_out_at` are derived from booking dates (14:00 / 12:00).
All actual operational timestamps (`actual_check_in_at`, `actual_check_out_at`) are left NULL until
the front-desk event occurs. The stay is created inside the existing DB transaction — if stay
creation fails, the confirmation rolls back.

**Historical confirmed bookings (pre-deployment):**
Bookings confirmed before this feature was deployed do not have Stay rows.
Run the bounded backfill command before first use of operational stay features:

```
php artisan stays:backfill-operational            # persist rows
php artisan stays:backfill-operational --dry-run  # count eligible, persist nothing
```

Selection criteria: `status = 'confirmed'` AND `check_out >= today` AND no existing stay row.
The command is idempotent and safe to re-run.
It does NOT fabricate `actual_check_in_at` or `actual_check_out_at` timestamps.
It does NOT touch `cancelled`, `refund_pending`, `refund_failed`, or past-checkout bookings.
Source: `app/Console/Commands/BackfillOperationalStays.php`

## Overlap Logic Preservation

The booking overlap constraint (`no_overlapping_bookings`, EXCLUDE USING gist) and all
associated application logic in `Booking.php`, `CancellationService.php` are **untouched**
by the four-layer domain model. These new tables are orthogonal to reservation date management.
