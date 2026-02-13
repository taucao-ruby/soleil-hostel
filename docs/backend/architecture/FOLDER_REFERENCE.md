# Backend Folder Reference

> Verified against `backend/` source code on February 12, 2026.
> Scope covers all folders requested: `Console/Commands`, `Database`, `Directives`, `Enums`, `Events`, `Exceptions`, `Helpers`, `Http`, `Jobs`, `Listeners`, `Logging`, `Macros`, `Models`, `Notifications`, `Observers`, `Octane`, `Tables`, `Policies`, `Providers`, `Repositories`, `Services`, `Traits`, `config`, `database`, `backups`, `factories`, `migrations`.

## Naming Notes

- `Polices` in the request maps to `backend/app/Policies`.
- `Respositories` in the request maps to `backend/app/Repositories`.

## Coverage Summary

| Folder | File Count |
| --- | --- |
| `backend/app/Console/Commands` | 3 |
| `backend/app/Database` | 3 |
| `backend/app/Directives` | 1 |
| `backend/app/Enums` | 2 |
| `backend/app/Events` | 6 |
| `backend/app/Exceptions` | 4 |
| `backend/app/Helpers` | 1 |
| `backend/app/Http` | 39 |
| `backend/app/Jobs` | 2 |
| `backend/app/Listeners` | 8 |
| `backend/app/Logging` | 3 |
| `backend/app/Macros` | 1 |
| `backend/app/Models` | 7 |
| `backend/app/Notifications` | 3 |
| `backend/app/Observers` | 1 |
| `backend/app/Octane` | 2 |
| `backend/app/Octane/Tables` | 1 |
| `backend/app/Policies` | 3 |
| `backend/app/Providers` | 7 |
| `backend/app/Repositories` | 4 |
| `backend/app/Services` | 9 |
| `backend/app/Traits` | 3 |
| `backend/config` | 20 |
| `backend/database` | 45 |
| `backend/database/backups` | 2 |
| `backend/database/factories` | 4 |
| `backend/database/migrations` | 32 |
| `backend/database/seeders` | 5 |

## `backend/app`

### `Console/Commands`

- `CacheWarmupCommand.php` - cache warmup orchestration and preflight checks.
- `OctaneNPlusOneMonitor.php` - Octane query metric monitor command.
- `PruneOldSoftDeletedBookings.php` - cleanup command for old soft deleted bookings.

### `Database`

- `IdempotencyGuard.php` - idempotent operation guard helpers.
- `TransactionIsolation.php` - transaction isolation and retry helpers.
- `TransactionMetrics.php` - transaction-level metrics recorder.

### `Directives`

- `PurifyDirective.php` - Blade directives for purified output.

### `Enums`

- `BookingStatus.php` - booking lifecycle states and transition helpers.
- `UserRole.php` - RBAC role enum and helpers.

### `Events`

- `BookingCancelled.php`
- `BookingCreated.php`
- `BookingDeleted.php`
- `BookingUpdated.php`
- `RateLimiterDegraded.php`
- `RequestThrottled.php`

### `Exceptions`

- `BookingCancellationException.php`
- `OptimisticLockException.php`
- `RefundFailedException.php`
- `TransactionExceptions.php` (contains `TransactionException` family classes).

### `Helpers`

- `SecurityHelpers.php` (`csp_nonce()` helper).

### `Http`

Controllers:

- `Controllers/AdminBookingController.php`
- `Controllers/AuthController.php`
- `Controllers/BookingController.php`
- `Controllers/ContactController.php`
- `Controllers/Controller.php`
- `Controllers/CspViolationReportController.php`
- `Controllers/HealthController.php`
- `Controllers/LocationController.php`
- `Controllers/RoomController.php`
- `Controllers/Auth/AuthController.php`
- `Controllers/Auth/EmailVerificationController.php`
- `Controllers/Auth/HttpOnlyTokenController.php`
- `Controllers/Auth/UnifiedAuthController.php`

Middleware:

- `Middleware/AddCorrelationId.php`
- `Middleware/AdvancedRateLimitMiddleware.php`
- `Middleware/CheckHttpOnlyTokenValid.php`
- `Middleware/CheckTokenNotRevokedAndNotExpired.php`
- `Middleware/Cors.php`
- `Middleware/DeprecatedEndpoint.php`
- `Middleware/EnsureUserHasRole.php`
- `Middleware/LogPerformance.php`
- `Middleware/SecurityHeaders.php`
- `Middleware/ThrottleApiRequests.php`

Form Requests:

- `Requests/ListRoomsRequest.php`
- `Requests/LoginRequest.php`
- `Requests/RefreshTokenRequest.php`
- `Requests/RegisterRequest.php`
- `Requests/RoomRequest.php`
- `Requests/StoreBookingRequest.php`
- `Requests/StoreReviewRequest.php`
- `Requests/UpdateBookingRequest.php`
- `Requests/UpdateReviewRequest.php`
- `Requests/Auth/LoginRequest.php`
- `Requests/Auth/RegisterRequest.php`

Resources and responses:

- `Resources/BookingResource.php`
- `Resources/LocationResource.php`
- `Resources/RoomResource.php`
- `Resources/UserResource.php`
- `Responses/ApiResponse.php`

### `Jobs`

- `CreateBookingJob.php`
- `ReconcileRefundsJob.php`

### `Listeners`

- `InvalidateCacheOnBookingChange.php`
- `InvalidateCacheOnBookingDeleted.php`
- `InvalidateCacheOnBookingUpdated.php`
- `InvalidateRoomAvailabilityCache.php`
- `QueryDebuggerListener.php`
- `SendBookingCancellation.php`
- `SendBookingConfirmation.php`
- `SendBookingUpdateNotification.php`

### `Logging`

- `ContextProcessor.php`
- `JsonFormatter.php`
- `SensitiveDataProcessor.php`

### `Macros`

- `FormRequestPurifyMacro.php`

### `Models`

- `Booking.php`
- `ContactMessage.php`
- `Location.php`
- `PersonalAccessToken.php`
- `Review.php`
- `Room.php`
- `User.php`

### `Notifications`

- `BookingCancelled.php`
- `BookingConfirmed.php`
- `BookingUpdated.php`

### `Observers`

- `BookingObserver.php`

### `Octane`

- `NPlusOneDetectionListener.php`
- `Tables/QueryMetricsTable.php`

### `Policies`

- `BookingPolicy.php`
- `ReviewPolicy.php`
- `RoomPolicy.php`

### `Providers`

- `AppServiceProvider.php`
- `AuthServiceProvider.php`
- `EventServiceProvider.php`
- `HorizonServiceProvider.php`
- `QueryLogServiceProvider.php`
- `RateLimiterServiceProvider.php`
- `RouteServiceProvider.php`

### `Repositories`

- `EloquentBookingRepository.php`
- `EloquentRoomRepository.php`
- `Contracts/BookingRepositoryInterface.php`
- `Contracts/RoomRepositoryInterface.php`

### `Services`

- `BookingService.php`
- `CancellationService.php`
- `CreateBookingService.php`
- `HtmlPurifierService.php`
- `RateLimitService.php`
- `RoomAvailabilityService.php`
- `RoomService.php`
- `Cache/CacheWarmer.php`
- `Cache/RoomAvailabilityCache.php`

### `Traits`

- `ApiResponse.php`
- `HasCacheTagSupport.php`
- `Purifiable.php`

## `backend/config`

- `app.php`
- `auth.php`
- `booking.php`
- `cache.php`
- `cors.php`
- `database.php`
- `email-branding.php`
- `filesystems.php`
- `horizon.php`
- `logging.php`
- `mail.php`
- `octane.php`
- `purifier.php`
- `query-detector.php`
- `queue.php`
- `rate-limits.php`
- `sanctum.php`
- `security-headers.php`
- `services.php`
- `session.php`

## `backend/database`

### Root files

- `.gitignore`
- `database.sqlite`

### Backups

- `backups/database_2025-12-01_124147.sqlite`
- `backups/database_2025-12-01_124458.sqlite`

### Factories

- `factories/BookingFactory.php`
- `factories/LocationFactory.php`
- `factories/RoomFactory.php`
- `factories/UserFactory.php`

### Migrations

- `migrations/0001_01_01_000000_create_users_table.php`
- `migrations/0001_01_01_000001_create_cache_table.php`
- `migrations/0001_01_01_000002_create_jobs_table.php`
- `migrations/2025_05_08_101021_create_personal_access_tokens_table.php`
- `migrations/2025_05_09_000000_create_rooms_table.php`
- `migrations/2025_05_09_074429_create_bookings_table.php`
- `migrations/2025_11_18_000000_add_user_id_to_bookings.php`
- `migrations/2025_11_18_000001_add_is_admin_to_users.php`
- `migrations/2025_11_18_000002_add_booking_constraints.php`
- `migrations/2025_11_20_000100_add_token_expiration_to_personal_access_tokens.php`
- `migrations/2025_11_20_100000_add_pessimistic_locking_indexes_bookings.php`
- `migrations/2025_11_21_150000_add_token_security_columns.php`
- `migrations/2025_11_24_000000_create_reviews_table.php`
- `migrations/2025_12_05_add_nplusone_fix_indexes.php`
- `migrations/2025_12_17_000001_convert_role_to_enum_and_drop_is_admin.php`
- `migrations/2025_12_18_000000_optimize_booking_indexes.php`
- `migrations/2025_12_18_100000_add_soft_deletes_to_bookings.php`
- `migrations/2025_12_18_200000_add_lock_version_to_rooms.php`
- `migrations/2026_01_11_000001_add_payment_fields_to_bookings.php`
- `migrations/2026_01_12_000000_add_booking_id_unique_constraint_to_reviews_table.php`
- `migrations/2026_02_09_000000_add_foreign_key_constraints.php`
- `migrations/2026_02_09_000001_create_locations_table.php`
- `migrations/2026_02_09_000002_add_location_id_to_rooms_table.php`
- `migrations/2026_02_09_000003_add_location_id_to_bookings_table.php`
- `migrations/2026_02_09_000004_seed_initial_locations.php`
- `migrations/2026_02_09_000005_assign_rooms_to_locations.php`
- `migrations/2026_02_09_000006_add_booking_location_trigger.php`
- `migrations/2026_02_10_000001_create_contact_messages_table.php`
- `migrations/2026_02_10_000002_make_booking_id_non_nullable_on_reviews.php`
- `migrations/2026_02_10_091954_add_cancellation_reason_to_bookings_table.php`
- `migrations/2026_02_11_000000_reconcile_legacy_index_ordering.php`
- `migrations/2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php`

### Seeders

- `seeders/DatabaseSeeder.php`
- `seeders/LocationSeeder.php`
- `seeders/ReviewSeeder.php`
- `seeders/RoomSeeder.php`
- `seeders/RoomsTableSeeder.php`
