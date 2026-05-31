# Booking Cancellation Flow with Refund Logic

> **Moved.** This document has been consolidated into the single canonical
> cancellation/refund reference:
>
> 👉 [`docs/backend/architecture/BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md`](architecture/BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md)

The previous contents (a January 2026 design draft) described a design that was
never built as written — Cashier-native `$user->refund()`, a `RefundPolicyService`,
`Cashier::handleWebhook()`, and a `202`/async controller. None of those exist in
the codebase. The canonical document above is the **as-built** architecture:
`CancellationService` (3-phase), a custom fail-closed `StripeWebhookController`,
`StripeService` refunds with stable idempotency keys, the `stripe_refund_events`
ledger, and `ReconcileRefundsJob`.

See also:

- Booking feature overview — [`features/BOOKING.md`](features/BOOKING.md)
- Column semantics & invariants — [`../agents/ARCHITECTURE_FACTS.md`](../agents/ARCHITECTURE_FACTS.md)
- Stuck-webhook reaper — [`STRIPE_WEBHOOK_RECONCILIATION.md`](STRIPE_WEBHOOK_RECONCILIATION.md)
