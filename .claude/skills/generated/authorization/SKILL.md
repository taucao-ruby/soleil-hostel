---
name: authorization
description: "Skill for the Authorization area of soleil-hostel. 24 symbols across 5 files."
---

# Authorization

24 symbols | 5 files | Cohesion: 49%

## When to Use

- Working with code in `backend/`
- Understanding how admin, isUser, test_is_user_returns_true_only_for_user_role work
- Modifying authorization-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Authorization/GateTest.php` | test_admin_gate_allows_admin, test_moderator_gate_allows_admin, test_view_all_bookings_gate_allows_moderator_and_above, test_gate_authorize_passes_for_authorized, test_gate_check_is_equivalent_to_allows (+5) |
| `backend/tests/Unit/Models/UserRoleHelpersTest.php` | test_is_user_returns_true_only_for_user_role, test_role_is_cast_to_enum, test_factory_admin_state, test_has_role_exact_match, test_factory_moderator_state |
| `backend/tests/Feature/Middleware/EnsureUserHasRoleTest.php` | test_admin_can_access_admin_route, test_admin_can_access_moderator_route, test_moderator_cannot_access_admin_route, test_moderator_can_access_moderator_route, test_all_authenticated_users_can_access_user_route |
| `backend/database/factories/UserFactory.php` | admin, moderator |
| `backend/app/Models/User.php` | isUser, hasRole |

## Entry Points

Start here when exploring this area:

- **`admin`** (Method) — `backend/database/factories/UserFactory.php:49`
- **`isUser`** (Method) — `backend/app/Models/User.php:89`
- **`test_is_user_returns_true_only_for_user_role`** (Method) — `backend/tests/Unit/Models/UserRoleHelpersTest.php:45`
- **`test_role_is_cast_to_enum`** (Method) — `backend/tests/Unit/Models/UserRoleHelpersTest.php:133`
- **`test_factory_admin_state`** (Method) — `backend/tests/Unit/Models/UserRoleHelpersTest.php:152`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `admin` | Method | `backend/database/factories/UserFactory.php` | 49 |
| `isUser` | Method | `backend/app/Models/User.php` | 89 |
| `test_is_user_returns_true_only_for_user_role` | Method | `backend/tests/Unit/Models/UserRoleHelpersTest.php` | 45 |
| `test_role_is_cast_to_enum` | Method | `backend/tests/Unit/Models/UserRoleHelpersTest.php` | 133 |
| `test_factory_admin_state` | Method | `backend/tests/Unit/Models/UserRoleHelpersTest.php` | 152 |
| `test_admin_can_access_admin_route` | Method | `backend/tests/Feature/Middleware/EnsureUserHasRoleTest.php` | 40 |
| `test_admin_can_access_moderator_route` | Method | `backend/tests/Feature/Middleware/EnsureUserHasRoleTest.php` | 71 |
| `test_admin_gate_allows_admin` | Method | `backend/tests/Feature/Authorization/GateTest.php` | 22 |
| `test_moderator_gate_allows_admin` | Method | `backend/tests/Feature/Authorization/GateTest.php` | 48 |
| `test_view_all_bookings_gate_allows_moderator_and_above` | Method | `backend/tests/Feature/Authorization/GateTest.php` | 110 |
| `test_gate_authorize_passes_for_authorized` | Method | `backend/tests/Feature/Authorization/GateTest.php` | 156 |
| `test_gate_check_is_equivalent_to_allows` | Method | `backend/tests/Feature/Authorization/GateTest.php` | 169 |
| `moderator` | Method | `backend/database/factories/UserFactory.php` | 59 |
| `hasRole` | Method | `backend/app/Models/User.php` | 101 |
| `test_has_role_exact_match` | Method | `backend/tests/Unit/Models/UserRoleHelpersTest.php` | 58 |
| `test_factory_moderator_state` | Method | `backend/tests/Unit/Models/UserRoleHelpersTest.php` | 158 |
| `test_moderator_cannot_access_admin_route` | Method | `backend/tests/Feature/Middleware/EnsureUserHasRoleTest.php` | 50 |
| `test_moderator_can_access_moderator_route` | Method | `backend/tests/Feature/Middleware/EnsureUserHasRoleTest.php` | 81 |
| `test_all_authenticated_users_can_access_user_route` | Method | `backend/tests/Feature/Middleware/EnsureUserHasRoleTest.php` | 101 |
| `test_admin_gate_denies_moderator` | Method | `backend/tests/Feature/Authorization/GateTest.php` | 30 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 15 calls |
| Booking | 5 calls |

## How to Explore

1. `gitnexus_context({name: "admin"})` — see callers and callees
2. `gitnexus_query({query: "authorization"})` — find related execution flows
3. Read key files listed above for implementation details
