<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds rooms across all locations. Requires LocationSeeder to run first.
     */
    public function run(): void
    {
        $rooms = [
            [
                'name' => 'Deluxe Room',
                'description' => 'Spacious room with city view',
                'price' => 150.00,
                'max_guests' => 2,
                'status' => 'available',
                'location_id' => 1,
                'room_number' => '101',
            ],
            [
                'name' => 'Suite Room',
                'description' => 'Luxury suite with separate living area',
                'price' => 250.00,
                'max_guests' => 4,
                'status' => 'available',
                'location_id' => 1,
                'room_number' => '102',
            ],
            [
                'name' => 'Standard Room',
                'description' => 'Comfortable room for single occupancy',
                'price' => 100.00,
                'max_guests' => 1,
                'status' => 'available',
                'location_id' => 1,
                'room_number' => '103',
            ],
        ];

        foreach ($rooms as $room) {
            Room::create($room);
        }
    }
}
