# Multi-Location Migration Guide

> Migration guide for Soleil Hostel multi-location architecture upgrade

## Overview

This guide documents the expansion from a single-location to multi-location architecture supporting 5 physical Soleil brand properties.

## Prerequisites

- PostgreSQL 16+ (production) or SQLite (testing)
- Laravel 11.x
- All existing migrations applied

## Migration Sequence

The upgrade consists of 6 new migrations that run in order:

| #   | Migration                                             | Description                               | Reversible |
| --- | ----------------------------------------------------- | ----------------------------------------- | ---------- |
| 1   | `2026_02_09_000001_create_locations_table`            | Create locations table with JSONB fields  | ✅         |
| 2   | `2026_02_09_000002_add_location_id_to_rooms_table`    | Add location_id + room_number to rooms    | ✅         |
| 3   | `2026_02_09_000003_add_location_id_to_bookings_table` | Add denormalized location_id to bookings  | ✅         |
| 4   | `2026_02_09_000004_seed_initial_locations`            | Seed 5 Soleil locations                   | ✅         |
| 5   | `2026_02_09_000005_assign_rooms_to_locations`         | Assign existing rooms + backfill bookings | ✅         |
| 6   | `2026_02_09_000006_add_booking_location_trigger`      | PostgreSQL trigger for auto-population    | ✅         |

## Zero-Downtime Deployment Strategy

### Phase 1: Schema Changes (Migrations 1-3)

These add NULLABLE columns and new tables. No data changes, no breaking changes.

```bash
php artisan migrate
```

The application continues to work because:

- `location_id` is NULLABLE on rooms and bookings
- No existing queries are affected
- No NOT NULL constraints yet

### Phase 2: Data Migration (Migrations 4-5)

- Seeds the 5 location records
- Assigns all existing rooms to "Soleil Hostel" (location_id = 1)
- Backfills `bookings.location_id` from `rooms.location_id`
- Makes `rooms.location_id` NOT NULL

### Phase 3: Trigger (Migration 6)

Adds PostgreSQL trigger for automatic `location_id` population on bookings.
This is a safety net complementing the application-level `BookingObserver`.

## Running the Migration

### Production

```bash
# 1. Backup database
pg_dump -U postgres -d soleil_hostel > backup_$(date +%Y%m%d_%H%M%S).sql

# 2. Run migrations
php artisan migrate --force

# 3. Verify
php artisan tinker --execute="echo App\Models\Location::count() . ' locations'"
php artisan tinker --execute="echo App\Models\Room::whereNull('location_id')->count() . ' rooms without location'"
```

### Development

```bash
# Fresh start with seed data
php artisan migrate:fresh --seed

# Or just run new migrations
php artisan migrate
php artisan db:seed --class=LocationSeeder
```

### Testing

Tests use SQLite in-memory. The `RefreshDatabase` trait handles migration automatically.

```bash
php artisan test --filter=LocationTest
php artisan test --filter=LocationApiTest
```

## Rollback Plan

```bash
# Rollback all 6 new migrations
php artisan migrate:rollback --step=6

# Or rollback to specific point
php artisan migrate:rollback --batch=N
```

### Production Rollback

```bash
# 1. Rollback migrations
php artisan migrate:rollback --step=6 --force

# 2. Or restore from backup
psql -U postgres -d soleil_hostel < backup_YYYYMMDD_HHMMSS.sql
```

## New API Endpoints

| Method | Endpoint                                | Auth   | Description              |
| ------ | --------------------------------------- | ------ | ------------------------ |
| GET    | `/api/v1/locations`                     | Public | List active locations    |
| GET    | `/api/v1/locations/{slug}`              | Public | Location detail + rooms  |
| GET    | `/api/v1/locations/{slug}/availability` | Public | Check room availability  |
| GET    | `/api/v1/rooms?location_id=X`           | Public | Filter rooms by location |

### No Breaking Changes

All existing API endpoints continue to work:

- `GET /api/v1/rooms` - Returns all rooms (now includes `location` and `location_id`)
- `POST /api/v1/bookings` - Creates booking (observer auto-populates `location_id`)
- All auth, booking, and admin endpoints are unchanged

## Data Model Changes

### New Fields on Existing Tables

**rooms:**

- `location_id` (BIGINT, NOT NULL, FK → locations)
- `room_number` (VARCHAR(50), NULLABLE)

**bookings:**

- `location_id` (BIGINT, NULLABLE, FK → locations) - denormalized for analytics

### Consistency Guarantees

`bookings.location_id` is automatically populated via:

1. **BookingObserver** (application level) - works in all environments
2. **PostgreSQL trigger** (database level) - production safety net

## Locations Seeded

| ID  | Name                     | Address                | City          | Rooms |
| --- | ------------------------ | ---------------------- | ------------- | ----- |
| 1   | Soleil Hostel            | 62 Tố Hữu              | Thành phố Huế | 9     |
| 2   | Soleil House             | 33 Lý Thường Kiệt      | Thành phố Huế | 10    |
| 3   | Soleil Urban Villa       | KDT BGI Topaz Downtown | Quảng Điền    | 7     |
| 4   | Soleil Boutique Homestay | 46 Lê Duẩn             | Thành phố Huế | 11    |
| 5   | Soleil Riverside Villa   | Quảng Phú              | Quảng Điền    | 8     |

## Frontend Routes

| Path               | Component        | Description                           |
| ------------------ | ---------------- | ------------------------------------- |
| `/locations`       | `LocationList`   | All locations grid with city filter   |
| `/locations/:slug` | `LocationDetail` | Location detail + availability search |
