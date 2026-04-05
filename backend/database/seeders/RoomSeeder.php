<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds rooms across all locations. Requires LocationSeeder to run first.
     * Prices are in VND. room_type_code and room_tier follow the classification
     * ladder used by equivalence/upgrade routing (see Room::scopeEquivalentTo,
     * Room::scopeUpgradeOver).
     *
     * Price bands:
     *   dorm      (tier 1, max_guests > 4)  → 350,000 VND
     *   standard  (tier 1, max_guests ≤ 2)  → 420,000 VND
     *   private   (tier 2, mid-range)        → 500,000 VND
     *   deluxe    (tier 2, superior)         → 550,000 VND
     *   villa     (tier 3)                   → 650,000 VND
     *   villa     (tier 4+)                  → 750,000 VND
     */
    public function run(): void
    {
        $rooms = [
            // ── Location 1 ────────────────────────────────────────────────
            [
                'name'            => 'Phòng Tiêu Chuẩn',
                'description'     => 'Phòng tiêu chuẩn thoải mái, phù hợp cho khách du lịch solo hoặc cặp đôi.',
                'price'           => 420000,
                'max_guests'      => 2,
                'status'          => 'available',
                'location_id'     => 1,
                'room_number'     => '101',
                'room_type_code'  => 'standard',
                'room_tier'       => 1,
            ],
            [
                'name'            => 'Phòng Ký Túc Xá',
                'description'     => 'Phòng dorm giá rẻ với giường tầng, lý tưởng cho khách ba lô.',
                'price'           => 350000,
                'max_guests'      => 6,
                'status'          => 'available',
                'location_id'     => 1,
                'room_number'     => '102',
                'room_type_code'  => 'dorm',
                'room_tier'       => 1,
            ],
            [
                'name'            => 'Phòng Cao Cấp',
                'description'     => 'Phòng cao cấp rộng rãi với tầm nhìn thành phố, tiện nghi hiện đại.',
                'price'           => 550000,
                'max_guests'      => 2,
                'status'          => 'available',
                'location_id'     => 1,
                'room_number'     => '103',
                'room_type_code'  => 'deluxe',
                'room_tier'       => 2,
            ],
            [
                'name'            => 'Phòng Riêng Tư',
                'description'     => 'Phòng riêng tư yên tĩnh, phù hợp cho cặp đôi hoặc gia đình nhỏ.',
                'price'           => 500000,
                'max_guests'      => 3,
                'status'          => 'available',
                'location_id'     => 1,
                'room_number'     => '104',
                'room_type_code'  => 'private',
                'room_tier'       => 2,
            ],
            [
                'name'            => 'Villa Sân Vườn',
                'description'     => 'Villa có sân vườn riêng, không gian nghỉ dưỡng sang trọng giữa thiên nhiên.',
                'price'           => 650000,
                'max_guests'      => 4,
                'status'          => 'available',
                'location_id'     => 1,
                'room_number'     => '105',
                'room_type_code'  => 'villa',
                'room_tier'       => 3,
            ],
            // ── Location 2 ────────────────────────────────────────────────
            [
                'name'            => 'Phòng Tiêu Chuẩn',
                'description'     => 'Phòng tiêu chuẩn sạch sẽ, đầy đủ tiện nghi cơ bản cho khách lưu trú ngắn ngày.',
                'price'           => 420000,
                'max_guests'      => 2,
                'status'          => 'available',
                'location_id'     => 2,
                'room_number'     => '201',
                'room_type_code'  => 'standard',
                'room_tier'       => 1,
            ],
            [
                'name'            => 'Phòng Ký Túc Xá',
                'description'     => 'Phòng dorm thoáng mát với đầy đủ ổ cắm điện và tủ khoá cá nhân.',
                'price'           => 350000,
                'max_guests'      => 8,
                'status'          => 'available',
                'location_id'     => 2,
                'room_number'     => '202',
                'room_type_code'  => 'dorm',
                'room_tier'       => 1,
            ],
            [
                'name'            => 'Phòng Cao Cấp Hướng Biển',
                'description'     => 'Phòng cao cấp view biển, ban công riêng, đồ nội thất cao cấp.',
                'price'           => 550000,
                'max_guests'      => 2,
                'status'          => 'available',
                'location_id'     => 2,
                'room_number'     => '203',
                'room_type_code'  => 'deluxe',
                'room_tier'       => 2,
            ],
            [
                'name'            => 'Villa Hồ Bơi',
                'description'     => 'Villa sang trọng với hồ bơi riêng, không gian mở hoàn toàn, tầm nhìn panorama.',
                'price'           => 750000,
                'max_guests'      => 5,
                'status'          => 'available',
                'location_id'     => 2,
                'room_number'     => '204',
                'room_type_code'  => 'villa',
                'room_tier'       => 4,
            ],
        ];

        foreach ($rooms as $room) {
            Room::create($room);
        }
    }
}
