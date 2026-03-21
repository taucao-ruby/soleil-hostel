# SOLEIL HOSTEL - Operational Domain - Architecture Notes (Pass 2)

## Room Readiness Layer

### Implemented - what is now live and source-verified

- `rooms` now carries `readiness_status`, `readiness_changed_at`, `readiness_changed_by`, and `out_of_service_reason`.
- `room_readiness_logs` is the append-only audit trail for readiness transitions.
- Canonical readiness enum values implemented in source:
  - `ready`
  - `occupied`
  - `dirty`
  - `cleaning`
  - `inspected`
  - `out_of_service`
- PostgreSQL CHECK constraints enforce readiness values on `rooms` and `room_readiness_logs`.
- Operationally available room queries now exclude `readiness_status = 'out_of_service'` while preserving booking overlap semantics.

### Automatic transitions wired

- `StayObserver` moves room readiness:
  - `in_house` -> `occupied`
  - `checked_out` -> `dirty`
- `RoomAssignmentObserver` blocks creation of an active assignment unless the target room is `ready`.
- `RoomObserver` initializes new rooms:
  - `status = 'maintenance'` -> `readiness_status = 'out_of_service'`
  - otherwise -> `readiness_status = 'ready'`

### Deferred - what is explicitly out of scope and why

- Housekeeping task scheduling: no task table or dispatch workflow exists in source.
- Housekeeping staff assignment and routing: no staff-routing model exists in source.
- SLA timer enforcement: no timer/escalation policy model exists in source.
- Guest-facing room-ready notifications: no notification workflow was added in this pass.

## Operational Dashboard Read Models

### Boards implemented and what each answers

- `arrivalDepartureBoard(locationId, date)`
  - expected arrivals
  - guests currently in house
  - due-outs
  - late checkouts
  - arrivals blocked by room readiness
  - confirmed bookings missing stay rows
- `roomReadinessBoard(locationId)`
  - grouped room readiness state counts
  - per-room readiness rows
  - stale `dirty` / `cleaning` rooms by configurable threshold
- `exceptionBoard(locationId, date)`
  - open recovery cases
  - late checkout blockers
  - rooms needing reassignment
  - internal relocation candidates
  - external relocation escalations
  - compensation pending settlement
- `operationalExposureBoard(locationId, dateFrom, dateTo)`
  - confirmed bookings without stay rows
  - unresolved recovery case count
  - unresolved refund and voucher exposure
  - relocation absorbed-cost exposure
  - deposit / advance total returns `null` because source fields are absent

### Fields returned as null due to missing source - and what schema addition would unblock them

- Room floor: source rooms schema has no floor column.
  - Minimum unblock: additive nullable `floor` column on `rooms`.
- Room zone: source rooms schema has no zone column.
  - Minimum unblock: additive nullable `zone` column on `rooms`.
- Deposit / advance totals: source bookings schema has no deposit or advance payment column.
  - Minimum unblock: additive integer cents column such as `deposit_amount` or `advance_amount` on `bookings`.

### Deferred boards or views

- No admin controller or dashboard API endpoint was added in this pass.
- The read-model layer is service-first and can be exposed by a future backoffice route without changing query semantics.

## Front-Desk Decision Workflow

### Supported blockage types

- `late_checkout`
- `room_dirty`
- `room_cleaning`
- `room_inspected_pending`
- `out_of_service`
- `no_assignment`

### Escalation path implemented

- The resolver detects blockage type from source-confirmed stay, assignment, and readiness state.
- `no_blockage` returns immediately and writes no records.
- `external_relocation_escalated` is implemented with `service_recovery_cases.escalated_at`.
- Steps requiring room type or tier matching are blocked by absent source schema:
  - equivalent same-location swap
  - complimentary upgrade
  - internal relocation matching

### What remains manual - and the minimum human action required for each step

- Equivalent room search:
  - Human must inspect rooms manually because `rooms` has no source-confirmed type or tier field.
- Complimentary upgrade:
  - Human must determine upgrade hierarchy manually because no room hierarchy field exists in source.
- Internal relocation recommendation:
  - Human must compare room acceptability across locations manually because no shared room type/tier field exists in source.
- External relocation escalation:
  - Human must arrange external accommodation and record final compensation or settlement decisions.

### Deferred automation

- Booking transfer execution between locations
- External hotel booking and payment coordination
- Guest communication dispatch
- SLA timer escalation
- Housekeeping dispatch from blockage detection

## Financial Operations Visibility

### Methods implemented

- `depositSummary(locationId, dateFrom, dateTo)`
- `compensationExposure(locationId, dateFrom, dateTo)`
- `relocationCostSummary(locationId, dateFrom, dateTo)`

### Source fields absent - declared honestly, not papered over

- Deposit / advance payment field on `bookings`: absent.
- Settlement timestamp field on `service_recovery_cases`: absent.
- Room type / tier field on `rooms`: absent, which also blocks automated operational relocation matching.

### Financial treatment boundary - what this layer is and is not

- This layer is operational visibility.
- It is not accounting recognition.
- It is not a general ledger.
- Refund, voucher, and relocation-cost numbers are operational exposure estimates from `service_recovery_cases`.
- Deposit reporting is intentionally blocked until a source deposit field exists.

### Deferred - what accounting integration would be required to complete it

- ~~Add a source-confirmed deposit or advance payment field to `bookings`.~~ → Done in Pass 3
- ~~Add settlement tracking to `service_recovery_cases` for relocation and compensation settlement closure.~~ → Done in Pass 3
- Add accounting integration for deferred revenue, recognition timing, and ledger posting.

---

## Pass 3 — Blocker Closure (Sprint 3, 2026-03-21)

### Room Classification
- Added: `room_type_code` (dormitory|private_single|private_double|private_twin|private_suite), `room_tier` (1=budget, 2=standard, 3=suite) to `rooms` table
- Migration: `2026_03_21_000003_add_room_classification_to_rooms_table`
- Enum: `App\Enums\RoomTypeCode` (string-backed PHP enum with `defaultTier()` method)
- Comparison semantics on `Room` model: `isEquivalentTo()`, `isUpgradeOver()`, `isCrossLocationEquivalentTo()`
- PostgreSQL CHECK constraints: `chk_rooms_room_type_code`, `chk_rooms_room_tier`
- Indexes: `idx_rooms_classification_availability` (location_id, type, tier, readiness), `idx_rooms_type_tier`
- `RoomFactory` updated: populates `room_type_code` and `room_tier` deterministically via `defaultTier()`
- Deferred: floor/zone, pricing per tier, capacity rules

### Blockage Resolver — Completed Escalation Path
- `CheckInBlockageResolver::ROOM_TYPE_FIELD` changed from `null` to `'room_type_code'`
- Now automated: equivalent_swap → complimentary_upgrade → internal_relocation_recommended → external_relocation_escalated
- Private `findCandidateRooms()` method extracts candidate room search (prevents N+1, reused by all steps)
- `attempted_steps` array in result records each step and outcome for audit trail
- `IncidentType` enum extended with `EQUIVALENT_SWAP`, `COMPLIMENTARY_UPGRADE` values
- CHECK constraint `chk_src_incident_type` updated via migration `2026_03_21_000006`
- Still manual: booking transfer execution (Step 3), external hotel booking (Step 4), guest notification

### Financial Settlement
- `bookings.deposit_amount` (BIGINT cents, default 0) + `bookings.deposit_collected_at` (timestamptz nullable) added
- Migration: `2026_03_21_000004_add_deposit_fields_to_bookings_table`
- Treatment boundary: `deposit_amount` ≠ recognized revenue. Deposit tracking is operational, NOT accounting.
- `service_recovery_cases.settlement_status` (unsettled|partially_settled|settled|waived) + `settled_at` + `settled_amount` (BIGINT cents) + `settlement_notes` added
- Migration: `2026_03_21_000005_add_settlement_fields_to_service_recovery_cases_table`
- PostgreSQL CHECK constraint: `chk_src_settlement_status`
- Enum: `App\Enums\SettlementStatus` (string-backed PHP enum)
- Settlement predicates on `ServiceRecoveryCase`: `isUnsettled()`, `isSettled()`, `outstandingAmount()`
- `FinancialOperationsService` constants updated: `DEPOSIT_AMOUNT_FIELD = 'deposit_amount'`, `SETTLEMENT_TIMESTAMP_FIELD = 'settled_at'`
- `depositSummary()` now returns real data instead of nulls
- `relocationCostSummary()`: `settlement_tracking_blocked` changed from `true` to `false`; uses `settlement_status` for case identification
- Deferred: revenue recognition, GL integration, accrual accounting

### Operational Exposure Board — Completed Metrics
- `OperationalDashboardService` constants updated: `ROOM_TYPE_FIELD = 'room_type_code'`, `BOOKING_DEPOSIT_FIELD = 'deposit_amount'`
- Now non-null: `deposit_or_advance_amount_total`, `bookings_with_deposit_count`, `bookings_without_deposit_count`, `settlement_status_breakdown`, `total_settled_amount`, `total_outstanding_exposure`
- Remains null/deferred: `recognized_revenue`, `net_pnl_per_case` (require accounting/GL integration)
