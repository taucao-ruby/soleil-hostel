<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rooms = [
            [
                'name' => 'Deluxe Single Room',
                'description' => 'Comfortable single room with city view',
                'price' => 100.00,
                'max_guests' => 1,
                'status' => 'available',
            ],
            [
                'name' => 'Deluxe Double Room',
                'description' => 'Spacious double room with balcony',
                'price' => 150.00,
                'max_guests' => 2,
                'status' => 'available',
            ],
            [
                'name' => 'Family Suite',
                'description' => 'Large suite perfect for families',
                'price' => 200.00,
                'max_guests' => 4,
                'status' => 'available',
            ],
            [
                'name' => 'Executive Suite',
                'description' => 'Luxurious suite with separate living area',
                'price' => 300.00,
                'max_guests' => 2,
                'status' => 'available',
            ],
            [
                'name' => 'Standard Twin Room',
                'description' => 'Comfortable room with two single beds',
                'price' => 120.00,
                'max_guests' => 2,
                'status' => 'available',
            ],
        ];

        foreach ($rooms as $room) {
            Room::create($room);
        }
    }
}
