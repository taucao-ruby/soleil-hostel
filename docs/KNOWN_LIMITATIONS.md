# Known Limitations

> Current system constraints, technical debt, and planned improvements

## Overview

This document tracks known limitations in the Soleil Hostel system. Each limitation includes:

- Impact level (Critical | High | Medium | Low)
- Affected components
- Workarounds (if any)
- Planned resolution (if any)

---

## Quick Summary

| ID                                                   | Limitation                                  | Impact | Status    |
| ---------------------------------------------------- | ------------------------------------------- | ------ | --------- |
| [LIM-001](#lim-001-single-database-region)           | Single database region                      | Medium | Accepted  |
| [LIM-002](#lim-002-no-payment-integration)           | No payment integration                      | High   | Planned   |
| [LIM-003](#lim-003-synchronous-refund-processing)    | Synchronous refund processing               | Medium | Accepted  |
| [LIM-004](#lim-004-no-multi-tenancy)                 | No multi-tenancy support                    | Low    | Accepted  |
| [LIM-005](#lim-005-redis-required-for-rate-limiting) | Redis required for advanced rate limiting   | Medium | Mitigated |
| [LIM-006](#lim-006-no-webhook-retry-mechanism)       | No webhook retry mechanism                  | Medium | Planned   |
| [LIM-007](#lim-007-email-delivery-not-guaranteed)    | Email delivery not guaranteed               | Medium | Accepted  |
| [LIM-008](#lim-008-no-internationalization)          | No internationalization (i18n)              | Low    | Planned   |
| [LIM-009](#lim-009-session-based-rate-limits)        | Session-based rate limits (not distributed) | Medium | Mitigated |
| [LIM-010](#lim-010-no-audit-log-retention-policy)    | No audit log retention policy               | Low    | Planned   |

---

## Database & Infrastructure

### LIM-001: Single Database Region

**Impact**: Medium  
**Status**: Accepted  
**Affected**: All data access

#### Description

The system uses a single PostgreSQL instance. No read replicas or multi-region deployment.

#### Consequences

- **Latency**: Users far from the database region experience slower responses
- **Availability**: Single point of failure for database
- **Scalability**: Vertical scaling only

#### Workarounds

- Redis caching reduces database load
- CDN for static assets
- Database connection pooling (PgBouncer)

#### Planned Resolution

None currently. Multi-region is expensive and premature for current scale.

---

### LIM-005: Redis Required for Advanced Rate Limiting

**Impact**: Medium  
**Status**: Mitigated  
**Affected**: `RateLimitService`

#### Description

Advanced rate limiting (sliding window, token bucket) requires Redis for atomic operations.

#### Consequences

- Without Redis, falls back to in-memory store
- In-memory store doesn't work across multiple servers
- Rate limits may be less accurate without Redis

#### Workarounds

```php
// RateLimitService automatically falls back
if (!$this->redisAvailable()) {
    return $this->checkWithMemory($key, $limits);
}
```

#### Mitigation

- Laravel's built-in rate limiter works with any cache driver
- Critical limits use database for persistence
- CI/CD tests use database cache successfully

---

## Payment & Billing

### LIM-002: No Payment Integration

**Impact**: High  
**Status**: Planned  
**Affected**: Booking flow, refunds

#### Description

Payment processing is **stubbed**. No actual Stripe/PayPal integration.

#### Consequences

- Cannot collect real payments
- Refund logic exists but doesn't process real refunds
- `CancellationService` assumes successful refunds

#### Current Behavior

```php
// CancellationService.php
private function processRefund(Booking $booking): RefundResult
{
    // TODO: Integrate with Stripe
    // Currently returns mock success
    return new RefundResult(success: true, amount: $booking->amount);
}
```

#### Planned Resolution

- **Phase 1**: Stripe integration for payments
- **Phase 2**: Webhook handling for payment events
- **Phase 3**: Refund automation

---

### LIM-003: Synchronous Refund Processing

**Impact**: Medium  
**Status**: Accepted  
**Affected**: `CancellationService`

#### Description

Refund processing is synchronous, blocking the cancellation request until Stripe responds.

#### Consequences

- User waits for Stripe API call (500ms-2s)
- If Stripe is slow/down, cancellation fails
- No automatic retry on failure

#### Workarounds

- Intermediate states: `refund_pending`, `refund_failed`
- `ReconcileRefundsJob` for manual recovery
- Admin can manually process failed refunds

#### Design Decision

Synchronous was chosen for:

- Immediate user feedback
- Simpler state management
- Lower infrastructure requirements

See [ADR-003](./ADR.md#adr-003-pessimistic-locking-for-bookings) for context.

---

## Scalability

### LIM-004: No Multi-Tenancy Support

**Impact**: Low  
**Status**: Accepted  
**Affected**: Data model

#### Description

System designed for single hostel. Cannot manage multiple properties in one installation.

#### Consequences

- Each hostel needs separate deployment
- No cross-property reporting
- No shared user accounts across properties

#### Planned Resolution

Not planned. Current scope is single-property management.

---

### LIM-009: Session-Based Rate Limits (Not Distributed)

**Impact**: Medium  
**Status**: Mitigated  
**Affected**: Multi-server deployments

#### Description

Without Redis, rate limiting state is per-server (in-memory), not distributed.

#### Consequences

- Load-balanced servers have independent rate limit counters
- User could make N requests to server A AND N requests to server B
- Effectively doubles rate limits

#### Workarounds

- Use Redis in production (recommended)
- Sticky sessions (not ideal)
- Lower limits to account for multiplication

#### Configuration

```env
# Production: Use Redis
CACHE_DRIVER=redis

# Development: Database is fine
CACHE_DRIVER=database
```

---

## Notifications & Communication

### LIM-006: No Webhook Retry Mechanism

**Impact**: Medium  
**Status**: Planned  
**Affected**: Third-party integrations

#### Description

No built-in webhook system for notifying external services of events.

#### Consequences

- Cannot push events to external systems
- Integrations must poll API
- No guaranteed delivery to partners

#### Planned Resolution

- Laravel Webhook Server package
- Exponential backoff retry
- Dead letter queue for failed webhooks

---

### LIM-007: Email Delivery Not Guaranteed

**Impact**: Medium  
**Status**: Accepted  
**Affected**: All notifications

#### Description

Email sending is fire-and-forget. No delivery confirmation or retry on failure.

#### Consequences

- Emails may silently fail
- No delivery tracking
- No open/click tracking

#### Mitigation

- Queue-based sending with `ShouldQueue`
- Failed jobs logged for investigation
- Rate limiting prevents email abuse

#### Planned Improvements

- Integrate with SendGrid/Mailgun for delivery tracking
- Implement bounce handling
- Add delivery status to booking details

---

## Internationalization

### LIM-008: No Internationalization (i18n)

**Impact**: Low  
**Status**: Planned  
**Affected**: UI, emails, error messages

#### Description

All text is hardcoded in English. No language switching capability.

#### Consequences

- English-only interface
- Cannot localize for other markets
- Date/currency formats fixed

#### Current Hardcoded Text

```php
// Notifications
->subject('🎉 Booking Confirmed - Soleil Hostel')

// Validation messages
'email' => 'The email must be a valid email address.'

// Error messages
'Room not available for selected dates'
```

#### Planned Resolution

- Laravel's `__()` helper for all strings
- Translation files in `resources/lang/`
- Date/currency localization via Carbon

---

## Data Management

### LIM-010: No Audit Log Retention Policy

**Impact**: Low  
**Status**: Planned  
**Affected**: Database size, compliance

#### Description

Audit logs (soft-deleted records, activity logs) grow indefinitely.

#### Consequences

- Database size grows continuously
- Backup times increase
- Query performance may degrade on large tables

#### Current State

- Soft-deleted bookings never purged
- No log rotation for application logs
- No data archival strategy

#### Planned Resolution

- Archive records older than X years
- Log rotation with Laravel's daily driver
- S3 archival for old bookings

---

## Technical Debt

### TD-001: Mixed Vietnamese/English Comments

**Status**: Low Priority  
**Affected**: Code readability

Some code comments are in Vietnamese (e.g., `CreateBookingService`). Should be standardized to English.

---

### TD-002: Inconsistent Error Response Format

**Status**: Medium Priority  
**Affected**: API consumers

Some errors return different structures:

```json
// Standard format
{"message": "Error message", "errors": {...}}

// Some exceptions
{"error": "Error message"}
```

**Resolution**: Standardize via exception handler.

---

### TD-003: Test Data Factories Not Comprehensive

**Status**: Low Priority  
**Affected**: Test isolation

Some factories don't cover all edge cases:

- No factory for expired bookings
- No factory for multi-day bookings with specific dates

---

## Feature Gaps

### FG-001: No Booking Modification History

Users cannot see history of changes to their booking.

### FG-002: No Guest Messaging System

No way for staff to message guests within the system.

### FG-003: No Booking Reminders

No automatic reminder emails before check-in.

### FG-004: No Waitlist for Sold-Out Dates

Users cannot join a waitlist for unavailable dates.

### FG-005: No Group Booking Support

Cannot book multiple rooms in a single transaction.

---

## Resolved Limitations

| ID          | Limitation               | Resolved In | How                           |
| ----------- | ------------------------ | ----------- | ----------------------------- |
| ~~LIM-011~~ | No email verification    | Jan 2026    | Implemented `MustVerifyEmail` |
| ~~LIM-012~~ | Boolean admin flags      | Dec 2025    | Replaced with `UserRole` enum |
| ~~LIM-013~~ | Generic email appearance | Jan 2026    | Branded Markdown templates    |

---

## Reporting New Limitations

When discovering a new limitation:

1. Add entry with unique ID (LIM-XXX)
2. Classify impact level
3. Document workarounds if any
4. Link to related ADRs if applicable
5. Update the quick summary table
