<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create locations table
 *
 * Phase 1 of multi-location architecture upgrade.
 * Stores physical property information for each Soleil brand location.
 *
 * Design decisions:
 * - JSONB for amenities/images: Flexible schema, GIN indexing support
 * - Slug for SEO-friendly URLs: /locations/soleil-hostel
 * - Coordinates for future Google Maps integration
 * - is_active for soft business closure (preserves historical data)
 * - lock_version for optimistic locking on concurrent admin updates
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('address');
            $table->string('city', 100);
            $table->string('district', 100)->nullable();
            $table->string('ward', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('amenities')->nullable();
            $table->jsonb('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('total_rooms')->default(0);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamps();

            // Indexes
            $table->index('is_active', 'idx_locations_active');
            $table->index(['city', 'district'], 'idx_locations_city_district');
        });

        // Conditional index for coordinates (PostgreSQL partial index)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                CREATE INDEX idx_locations_coordinates 
                ON locations (latitude, longitude) 
                WHERE latitude IS NOT NULL
            ');
        }

        // Check constraints
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                ALTER TABLE locations
                ADD CONSTRAINT chk_locations_total_rooms CHECK (total_rooms >= 0)
            ');
            DB::statement('
                ALTER TABLE locations
                ADD CONSTRAINT chk_locations_coordinates CHECK (
                    (latitude IS NULL AND longitude IS NULL) OR
                    (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)
                )
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
