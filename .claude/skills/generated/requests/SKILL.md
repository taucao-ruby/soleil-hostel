---
name: requests
description: "Skill for the Requests area of soleil-hostel. 26 symbols across 9 files."
---

# Requests

26 symbols | 9 files | Cohesion: 98%

## When to Use

- Working with code in `backend/`
- Understanding how UpdateBookingRequest, UpdateReviewRequest, success work
- Modifying requests-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | rules, test_guest_name_requires_minimum_2_characters, test_guest_name_passes_with_2_characters, test_room_id_is_optional_on_update, test_room_id_validated_when_provided (+4) |
| `backend/tests/Unit/Requests/ReviewRequestPurificationTest.php` | makeStoreRequest, test_store_review_validated_does_not_crash, test_store_review_validated_strips_xss, test_store_review_validated_with_key_returns_single_value, test_update_review_validated_does_not_crash (+2) |
| `backend/app/Http/Requests/LoginRequest.php` | shouldRemember, getDeviceName, getEmail, getPassword |
| `backend/app/Traits/ApiResponse.php` | success |
| `backend/app/Http/Controllers/ContactController.php` | store |
| `backend/app/Http/Controllers/AuthController.php` | register |
| `backend/app/Http/Controllers/Auth/AuthController.php` | login |
| `backend/app/Http/Requests/UpdateBookingRequest.php` | UpdateBookingRequest |
| `backend/app/Http/Requests/UpdateReviewRequest.php` | UpdateReviewRequest |

## Entry Points

Start here when exploring this area:

- **`UpdateBookingRequest`** (Class) — `backend/app/Http/Requests/UpdateBookingRequest.php:6`
- **`UpdateReviewRequest`** (Class) — `backend/app/Http/Requests/UpdateReviewRequest.php:12`
- **`success`** (Method) — `backend/app/Traits/ApiResponse.php:11`
- **`shouldRemember`** (Method) — `backend/app/Http/Requests/LoginRequest.php:92`
- **`getDeviceName`** (Method) — `backend/app/Http/Requests/LoginRequest.php:100`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `UpdateBookingRequest` | Class | `backend/app/Http/Requests/UpdateBookingRequest.php` | 6 |
| `UpdateReviewRequest` | Class | `backend/app/Http/Requests/UpdateReviewRequest.php` | 12 |
| `success` | Method | `backend/app/Traits/ApiResponse.php` | 11 |
| `shouldRemember` | Method | `backend/app/Http/Requests/LoginRequest.php` | 92 |
| `getDeviceName` | Method | `backend/app/Http/Requests/LoginRequest.php` | 100 |
| `getEmail` | Method | `backend/app/Http/Requests/LoginRequest.php` | 123 |
| `getPassword` | Method | `backend/app/Http/Requests/LoginRequest.php` | 131 |
| `store` | Method | `backend/app/Http/Controllers/ContactController.php` | 27 |
| `register` | Method | `backend/app/Http/Controllers/AuthController.php` | 30 |
| `login` | Method | `backend/app/Http/Controllers/Auth/AuthController.php` | 59 |
| `rules` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 15 |
| `test_guest_name_requires_minimum_2_characters` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 20 |
| `test_guest_name_passes_with_2_characters` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 36 |
| `test_room_id_is_optional_on_update` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 51 |
| `test_room_id_validated_when_provided` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 66 |
| `makeRequest` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 90 |
| `test_update_booking_validated_strips_xss_from_guest_name` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 112 |
| `test_update_booking_validated_does_not_alter_domain_fields` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 132 |
| `test_update_booking_validated_with_key_returns_single_value` | Method | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 156 |
| `makeStoreRequest` | Method | `backend/tests/Unit/Requests/ReviewRequestPurificationTest.php` | 20 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Login → IsRevoked` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Auth | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "UpdateBookingRequest"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "requests"})` — find related execution flows
3. Read key files listed above for implementation details
