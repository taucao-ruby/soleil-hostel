# 🗑️ Booking Soft Deletes Implementation

**Implemented:** December 18, 2025  
**Status:** ✅ Complete (19 new tests)

---

## 📋 Overview

Soft deletes for the Bookings entity preserves a complete audit trail while allowing:

-   **Data Preservation**: No booking data is ever permanently deleted
-   **Audit Trail**: Track who deleted what and when
-   **Recovery**: Admins can restore accidentally deleted bookings
-   **Compliance**: GDPR, SOX, and accounting requirements supported
-   **Referential Integrity**: Related records (Users, Rooms) remain intact

---

## 🏗️ Architecture

### Database Changes

```sql
-- Migration: 2025_12_18_100000_add_soft_deletes_to_bookings.php
ALTER TABLE bookings ADD COLUMN deleted_at TIMESTAMP NULL;
ALTER TABLE bookings ADD COLUMN deleted_by BIGINT UNSIGNED NULL;

-- Indexes for performance
CREATE INDEX idx_bookings_deleted_at ON bookings(deleted_at);
CREATE INDEX idx_bookings_soft_delete_audit ON bookings(deleted_at, deleted_by);
```

### Model Changes

```php
// app/Models/Booking.php
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [..., 'deleted_by'];

    // Audit relationship
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Soft delete with audit
    public function softDeleteWithAudit(?int $userId = null): bool;

    // Restore and clear audit
    public function restoreWithAudit(): bool;
}
```

---

## 🔧 API Endpoints

### Regular User Endpoints (Existing)

| Method | Endpoint             | Description                        |
| ------ | -------------------- | ---------------------------------- |
| DELETE | `/api/bookings/{id}` | Soft delete (now "Cancel Booking") |

### Admin Endpoints (New)

| Method | Endpoint                           | Description                           |
| ------ | ---------------------------------- | ------------------------------------- |
| GET    | `/api/admin/bookings`              | View all bookings (including trashed) |
| GET    | `/api/admin/bookings/trashed`      | View only soft-deleted bookings       |
| GET    | `/api/admin/bookings/trashed/{id}` | View specific trashed booking         |
| POST   | `/api/admin/bookings/{id}/restore` | Restore a trashed booking             |
| POST   | `/api/admin/bookings/restore-bulk` | Bulk restore multiple bookings        |
| DELETE | `/api/admin/bookings/{id}/force`   | Permanently delete (GDPR)             |

### Response Examples

**Trashed Booking Response:**

```json
{
    "success": true,
    "data": {
        "id": 123,
        "room_id": 1,
        "guest_name": "John Doe",
        "status": "confirmed",
        "is_trashed": true,
        "deleted_at": "2025-12-18T10:30:00+00:00",
        "deleted_by": {
            "id": 5,
            "name": "Admin User",
            "email": "admin@example.com"
        }
    }
}
```

---

## 🔒 Authorization (Policy)

| Action             | USER | MODERATOR | ADMIN |
| ------------------ | ---- | --------- | ----- |
| Delete own booking | ✅   | ✅        | ✅    |
| Delete any booking | ❌   | ❌        | ✅    |
| View trashed       | ❌   | ❌        | ✅    |
| Restore booking    | ❌   | ❌        | ✅    |
| Force delete       | ❌   | ❌        | ✅    |

---

## 📊 Service Layer

### BookingService Methods

```php
// Soft delete with audit trail
$bookingService->softDelete($booking, $deletedByUserId);

// Restore a trashed booking
$bookingService->restore($booking);

// Permanent delete (GDPR)
$bookingService->forceDelete($booking);

// Get trashed bookings
$bookingService->getTrashedBookings($page);

// Get specific trashed booking
$bookingService->getTrashedBookingById($id);
```

### Cache Invalidation

-   Trashed bookings have dedicated cache tag: `trashed-bookings`
-   TTL: 3 minutes (shorter for admin views)
-   Auto-invalidated on restore/force-delete

---

## 🧪 Testing

### New Test File

`tests/Feature/Booking/BookingSoftDeleteTest.php` (19 tests)

### Test Coverage

| Test                                                 | Description                         |
| ---------------------------------------------------- | ----------------------------------- |
| `test_delete_uses_soft_delete_and_preserves_data`    | Verifies soft delete preserves data |
| `test_soft_deleted_bookings_excluded_from_index`     | Confirms filtering works            |
| `test_soft_delete_records_audit_trail`               | Checks deleted_by is recorded       |
| `test_admin_can_view_trashed_bookings`               | Admin trash view                    |
| `test_admin_can_restore_trashed_booking`             | Restore functionality               |
| `test_restore_fails_with_date_conflict`              | Prevents overlapping bookings       |
| `test_admin_can_force_delete_trashed_booking`        | Permanent deletion                  |
| `test_soft_deleted_bookings_dont_block_new_bookings` | Availability check                  |
| `test_deleted_by_relationship`                       | Audit relationship                  |
| ... and 10 more                                      |

### Running Tests

```bash
# Run soft delete tests only
php artisan test --filter=BookingSoftDeleteTest

# Run all tests
php artisan test
```

---

## 🔄 Migration Guide

### Deploy Steps

1. **Run migration:**

    ```bash
    php artisan migrate
    ```

2. **Clear cache:**

    ```bash
    php artisan cache:clear
    ```

3. **Verify:**
    ```bash
    php artisan test --filter=BookingSoftDeleteTest
    ```

### Rollback

```bash
php artisan migrate:rollback --step=1
```

---

## 🧹 Pruning Old Records

### Console Command

```bash
# Preview (dry run)
php artisan bookings:prune-deleted --dry-run

# Prune after 7 years (default)
php artisan bookings:prune-deleted

# Custom retention period
php artisan bookings:prune-deleted --days=365
```

### Scheduling

Add to `app/Console/Kernel.php`:

```php
$schedule->command('bookings:prune-deleted')->weekly();
```

---

## ⚠️ Potential Pitfalls & Mitigations

### 1. Query Performance

**Issue:** Growing soft-deleted records slow down queries.

**Mitigation:**

-   Index on `deleted_at` for fast filtering
-   Pruning command removes old records
-   Queries automatically exclude deleted (via global scope)

### 2. Unique Constraint Violations on Restore

**Issue:** Restoring a booking may conflict with new bookings.

**Mitigation:**

-   Restore endpoint checks for overlapping dates before restoring
-   Returns 422 with clear error message if conflict exists

### 3. GDPR "Right to Be Forgotten"

**Issue:** Soft delete preserves data, conflicting with data deletion requests.

**Mitigation:**

-   `forceDelete` endpoint for admin-only permanent deletion
-   Audit log before force delete
-   Clear documentation for GDPR compliance

### 4. Cascading Deletes

**Issue:** Related entities (Payments, Invoices) shouldn't be affected.

**Mitigation:**

-   Soft delete only marks `deleted_at`
-   No foreign key cascade deletes
-   Related records remain intact for audit

---

## 📝 Compliance Notes

| Requirement       | Implementation                                     |
| ----------------- | -------------------------------------------------- |
| **GDPR**          | Force delete available for "right to be forgotten" |
| **SOX**           | 7-year default retention, audit trail preserved    |
| **Audit Trail**   | `deleted_at` and `deleted_by` recorded             |
| **Data Recovery** | Restore functionality for accidental deletions     |

---

## 📁 Files Changed

| File                                                                     | Change                                              |
| ------------------------------------------------------------------------ | --------------------------------------------------- |
| `database/migrations/2025_12_18_100000_add_soft_deletes_to_bookings.php` | New migration                                       |
| `app/Models/Booking.php`                                                 | Added SoftDeletes trait, audit methods              |
| `app/Services/BookingService.php`                                        | Added soft delete/restore/forceDelete methods       |
| `app/Http/Controllers/BookingController.php`                             | Updated destroy() for soft delete                   |
| `app/Http/Controllers/AdminBookingController.php`                        | New controller for admin operations                 |
| `app/Policies/BookingPolicy.php`                                         | Added restore, forceDelete, viewTrashed permissions |
| `app/Http/Resources/BookingResource.php`                                 | Added is_trashed, deleted_at, deleted_by            |
| `routes/api.php`                                                         | Added admin booking routes                          |
| `database/factories/BookingFactory.php`                                  | Added trashed() state                               |
| `app/Console/Commands/PruneOldSoftDeletedBookings.php`                   | New pruning command                                 |
| `tests/Feature/Booking/BookingSoftDeleteTest.php`                        | New test file (19 tests)                            |

---

## ✅ Verification

```bash
# All 272 tests passing
php artisan test

# Soft delete specific tests
php artisan test --filter=BookingSoftDeleteTest
# ✅ 19 passed (55 assertions)
```

---

**Status:** Production Ready ✅
