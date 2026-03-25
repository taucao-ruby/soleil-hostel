---
name: policies
description: "Skill for the Policies area of soleil-hostel. 65 symbols across 13 files."
---

# Policies

65 symbols | 13 files | Cohesion: 69%

## When to Use

- Working with code in `backend/`
- Understanding how Booking, Review, User work
- Modifying policies-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Unit/Policies/ReviewPolicyTest.php` | create_allowed_for_owner_with_confirmed_completed_booking, create_denied_for_non_owner, create_denied_for_pending_booking, create_denied_for_cancelled_booking, create_denied_before_checkout (+15) |
| `backend/tests/Feature/Policies/RoomPolicyTest.php` | test_create_returns_true_for_admin, test_create_returns_false_for_moderator, test_create_returns_false_for_user, test_update_returns_true_for_admin, test_update_returns_false_for_moderator (+13) |
| `backend/app/Policies/BookingPolicy.php` | update, delete, viewTrashed, restore, forceDelete (+1) |
| `backend/app/Policies/RoomPolicy.php` | update, create, delete, viewAny, view |
| `backend/app/Policies/ReviewPolicy.php` | create, delete, view, before, viewAny |
| `backend/app/Models/User.php` | isAdmin, User |
| `backend/tests/Unit/Models/BookingFillableTest.php` | test_cancellation_reason_is_fillable, test_cancellation_audit_fields_all_fillable |
| `backend/app/Models/Review.php` | Review, getPurifiableFields |
| `backend/tests/Unit/Models/UserRoleHelpersTest.php` | test_is_admin_returns_true_only_for_admin |
| `backend/app/Http/Requests/UpdateReviewRequest.php` | authorize |

## Entry Points

Start here when exploring this area:

- **`Booking`** (Class) — `backend/app/Models/Booking.php:14`
- **`Review`** (Class) — `backend/app/Models/Review.php:15`
- **`User`** (Class) — `backend/app/Models/User.php:15`
- **`update`** (Method) — `backend/app/Policies/RoomPolicy.php:36`
- **`update`** (Method) — `backend/app/Policies/BookingPolicy.php:24`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `Booking` | Class | `backend/app/Models/Booking.php` | 14 |
| `Review` | Class | `backend/app/Models/Review.php` | 15 |
| `User` | Class | `backend/app/Models/User.php` | 15 |
| `update` | Method | `backend/app/Policies/RoomPolicy.php` | 36 |
| `update` | Method | `backend/app/Policies/BookingPolicy.php` | 24 |
| `delete` | Method | `backend/app/Policies/BookingPolicy.php` | 33 |
| `viewTrashed` | Method | `backend/app/Policies/BookingPolicy.php` | 61 |
| `restore` | Method | `backend/app/Policies/BookingPolicy.php` | 70 |
| `forceDelete` | Method | `backend/app/Policies/BookingPolicy.php` | 79 |
| `confirm` | Method | `backend/app/Policies/BookingPolicy.php` | 88 |
| `isAdmin` | Method | `backend/app/Models/User.php` | 67 |
| `test_is_admin_returns_true_only_for_admin` | Method | `backend/tests/Unit/Models/UserRoleHelpersTest.php` | 19 |
| `authorize` | Method | `backend/app/Http/Requests/UpdateReviewRequest.php` | 17 |
| `destroy` | Method | `backend/app/Http/Controllers/ReviewController.php` | 90 |
| `create` | Method | `backend/app/Policies/ReviewPolicy.php` | 65 |
| `create_allowed_for_owner_with_confirmed_completed_booking` | Method | `backend/tests/Unit/Policies/ReviewPolicyTest.php` | 37 |
| `create_denied_for_non_owner` | Method | `backend/tests/Unit/Policies/ReviewPolicyTest.php` | 53 |
| `create_denied_for_pending_booking` | Method | `backend/tests/Unit/Policies/ReviewPolicyTest.php` | 70 |
| `create_denied_for_cancelled_booking` | Method | `backend/tests/Unit/Policies/ReviewPolicyTest.php` | 87 |
| `create_denied_before_checkout` | Method | `backend/tests/Unit/Policies/ReviewPolicyTest.php` | 104 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Cancel → IsAdmin` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Authorization | 1 calls |
| Models | 1 calls |

## How to Explore

1. `gitnexus_context({name: "Booking"})` — see callers and callees
2. `gitnexus_query({query: "policies"})` — find related execution flows
3. Read key files listed above for implementation details
