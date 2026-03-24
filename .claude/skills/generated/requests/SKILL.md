---
name: requests
description: "Skill for the Requests area of soleil-hostel. 21 symbols across 8 files."
---

# Requests

21 symbols | 8 files | Cohesion: 93%

## When to Use

- Working with code in `backend/`
- Understanding how UpdateBookingRequest, UpdateReviewRequest, rules work
- Modifying requests-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Unit/Requests/ReviewRequestPurificationTest.php` | makeStoreRequest, test_store_review_validated_does_not_crash, test_store_review_validated_strips_xss, test_store_review_validated_with_key_returns_single_value, test_update_review_validated_does_not_crash (+2) |
| `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | rules, test_guest_name_requires_minimum_2_characters, test_guest_name_passes_with_2_characters, test_room_id_is_optional_on_update, test_room_id_validated_when_provided |
| `backend/app/Http/Requests/LoginRequest.php` | shouldRemember, getDeviceName, getEmail, getPassword |
| `backend/app/Http/Requests/UpdateBookingRequest.php` | UpdateBookingRequest |
| `backend/app/Http/Requests/StoreReviewRequest.php` | validated |
| `backend/app/Http/Controllers/ReviewController.php` | store |
| `backend/app/Http/Controllers/Auth/AuthController.php` | login |
| `backend/app/Http/Requests/UpdateReviewRequest.php` | UpdateReviewRequest |

## Entry Points

Start here when exploring this area:

- **`UpdateBookingRequest`** (Class) — `backend/app/Http/Requests/UpdateBookingRequest.php:6`
- **`UpdateReviewRequest`** (Class) — `backend/app/Http/Requests/UpdateReviewRequest.php:12`
- **`rules`** (Method) — `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php:15`
- **`test_guest_name_requires_minimum_2_characters`** (Method) — `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php:20`
- **`test_guest_name_passes_with_2_characters`** (Method) — `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php:36`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `UpdateBookingRequest` | Class | `backend/app/Http/Requests/UpdateBookingRequest.php` | 6 |
| `UpdateReviewRequest` | Class | `backend/app/Http/Requests/UpdateReviewRequest.php` | 12 |
| `rules` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 15 |
| `test_guest_name_requires_minimum_2_characters` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 20 |
| `test_guest_name_passes_with_2_characters` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 36 |
| `test_room_id_is_optional_on_update` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 51 |
| `test_room_id_validated_when_provided` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 66 |
| `makeStoreRequest` | Method | `backend/tests/Unit/Requests/ReviewRequestPurificationTest.php` | 20 |
| `test_store_review_validated_does_not_crash` | Method | `backend/tests/Unit/Requests/ReviewRequestPurificationTest.php` | 39 |
| `test_store_review_validated_strips_xss` | Method | `backend/tests/Unit/Requests/ReviewRequestPurificationTest.php` | 58 |
| `test_store_review_validated_with_key_returns_single_value` | Method | `backend/tests/Unit/Requests/ReviewRequestPurificationTest.php` | 75 |
| `validated` | Method | `backend/app/Http/Requests/StoreReviewRequest.php` | 53 |
| `store` | Method | `backend/app/Http/Controllers/ReviewController.php` | 33 |
| `shouldRemember` | Method | `backend/app/Http/Requests/LoginRequest.php` | 92 |
| `getDeviceName` | Method | `backend/app/Http/Requests/LoginRequest.php` | 100 |
| `getEmail` | Method | `backend/app/Http/Requests/LoginRequest.php` | 123 |
| `getPassword` | Method | `backend/app/Http/Requests/LoginRequest.php` | 131 |
| `login` | Method | `backend/app/Http/Controllers/Auth/AuthController.php` | 59 |
| `test_update_review_validated_does_not_crash` | Method | `backend/tests/Unit/Requests/ReviewRequestPurificationTest.php` | 88 |
| `test_update_review_validated_strips_xss` | Method | `backend/tests/Unit/Requests/ReviewRequestPurificationTest.php` | 108 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Store → GetInstance` | cross_community | 4 |
| `Store → DoPurify` | cross_community | 4 |
| `Login → IsRevoked` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Security | 1 calls |
| Auth | 1 calls |
| Controllers | 1 calls |

## How to Explore

1. `gitnexus_context({name: "UpdateBookingRequest"})` — see callers and callees
2. `gitnexus_query({query: "requests"})` — find related execution flows
3. Read key files listed above for implementation details
