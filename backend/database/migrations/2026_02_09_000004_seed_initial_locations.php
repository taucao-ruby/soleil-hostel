<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration: Seed initial 5 Soleil locations
 *
 * Phase 2 data migration - populates locations with real business data.
 * These are the 5 physical Soleil brand properties across Hue City, Vietnam.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('locations')->insert([
            [
                'id' => 1,
                'name' => 'Soleil Hostel',
                'slug' => 'soleil-hostel',
                'address' => 'Tháp B, 62 Tố Hữu',
                'city' => 'Thành phố Huế',
                'district' => 'Huế',
                'ward' => 'Tháng 6',
                'postal_code' => '530000',
                'latitude' => 16.46370000,
                'longitude' => 107.59090000,
                'phone' => '+84 234 123 4567',
                'email' => 'hostel@soleil.vn',
                'description' => 'Our flagship hostel in the heart of Hue City. Perfect for backpackers and solo travelers seeking an authentic Vietnamese experience.',
                'amenities' => json_encode(['wifi', 'air_conditioning', 'hot_water', 'breakfast', 'parking', 'laundry']),
                'images' => json_encode([]),
                'is_active' => true,
                'total_rooms' => 9,
                'lock_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'Soleil House',
                'slug' => 'soleil-house',
                'address' => '33 Lý Thường Kiệt',
                'city' => 'Thành phố Huế',
                'district' => 'Phú Nhuận',
                'ward' => null,
                'postal_code' => '530000',
                'latitude' => 16.46230000,
                'longitude' => 107.59340000,
                'phone' => '+84 234 123 4568',
                'email' => 'house@soleil.vn',
                'description' => 'A cozy guesthouse in the Phu Nhuan district. Ideal for couples and small families looking for a comfortable stay.',
                'amenities' => json_encode(['wifi', 'air_conditioning', 'hot_water', 'breakfast', 'parking', 'garden']),
                'images' => json_encode([]),
                'is_active' => true,
                'total_rooms' => 10,
                'lock_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => 'Soleil Urban Villa',
                'slug' => 'soleil-urban-villa',
                'address' => 'KDT BGI Topaz Downtown',
                'city' => 'Quảng Điền',
                'district' => 'Quảng Điền',
                'ward' => null,
                'postal_code' => '530000',
                'latitude' => 16.52810000,
                'longitude' => 107.59120000,
                'phone' => '+84 234 123 4569',
                'email' => 'urbanvilla@soleil.vn',
                'description' => 'Modern urban villa in the BGI Topaz Downtown development. Perfect for those seeking contemporary comfort with city views.',
                'amenities' => json_encode(['wifi', 'air_conditioning', 'hot_water', 'pool', 'parking', 'gym', 'breakfast']),
                'images' => json_encode([]),
                'is_active' => true,
                'total_rooms' => 7,
                'lock_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'name' => 'Soleil Boutique Homestay',
                'slug' => 'soleil-boutique-homestay',
                'address' => '46 Lê Duẩn',
                'city' => 'Thành phố Huế',
                'district' => 'Phú Hoà',
                'ward' => null,
                'postal_code' => '530000',
                'latitude' => 16.46180000,
                'longitude' => 107.59520000,
                'phone' => '+84 234 123 4570',
                'email' => 'boutique@soleil.vn',
                'description' => 'A charming boutique homestay on Le Duan Street. Experience local culture with the comforts of a modern home.',
                'amenities' => json_encode(['wifi', 'air_conditioning', 'hot_water', 'breakfast', 'parking', 'garden', 'bbq']),
                'images' => json_encode([]),
                'is_active' => true,
                'total_rooms' => 11,
                'lock_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'name' => 'Soleil Riverside Villa',
                'slug' => 'soleil-riverside-villa',
                'address' => 'Quảng Phú',
                'city' => 'Quảng Điền',
                'district' => 'Quảng Điền',
                'ward' => 'Quảng Phú',
                'postal_code' => '530000',
                'latitude' => 16.53420000,
                'longitude' => 107.58870000,
                'phone' => '+84 234 123 4571',
                'email' => 'riverside@soleil.vn',
                'description' => 'A serene riverside villa near the Bo River. Perfect escape from the city with stunning water views and natural surroundings.',
                'amenities' => json_encode(['wifi', 'air_conditioning', 'hot_water', 'pool', 'parking', 'garden', 'kayaking', 'fishing', 'breakfast']),
                'images' => json_encode([]),
                'is_active' => true,
                'total_rooms' => 8,
                'lock_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // Reset sequence for PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval('locations_id_seq', (SELECT MAX(id) FROM locations))");
        }
    }

    public function down(): void
    {
        DB::table('locations')->whereIn('id', [1, 2, 3, 4, 5])->delete();
    }
};
