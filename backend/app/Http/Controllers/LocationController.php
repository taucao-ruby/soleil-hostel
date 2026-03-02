<?php

namespace App\Http\Controllers;

use App\Http\Requests\LocationAvailabilityRequest;
use App\Http\Requests\ShowLocationRequest;
use App\Http\Resources\LocationResource;
use App\Http\Resources\RoomResource;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LocationController
 *
 * Public API for browsing Soleil locations and checking room availability.
 * All endpoints are read-only and do not require authentication.
 */
class LocationController extends Controller
{
    /**
     * List all active locations with room counts.
     *
     * GET /api/v1/locations
     *
     * Optional query params:
     * - has_coordinates: boolean - filter to locations with map coordinates
     */
    public function index(Request $request): JsonResponse
    {
        $locations = Location::query()
            ->active()
            ->withRoomCounts()
            ->when($request->boolean('has_coordinates'), fn ($q) => $q->whereNotNull('latitude')->whereNotNull('longitude')
            )
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => __('messages.location_list_fetched'),
            'data' => LocationResource::collection($locations),
        ]);
    }

    /**
     * Show a single location with its rooms.
     *
     * GET /api/v1/locations/{slug}
     *
     * Optional query params:
     * - check_in: date - filter rooms available after this date
     * - check_out: date - filter rooms available before this date
     * - guests: int - filter rooms with sufficient capacity
     */
    public function show(string $slug, ShowLocationRequest $request): JsonResponse
    {

        $location = Location::query()
            ->where('slug', $slug)
            ->active()
            ->with(['rooms' => function ($query) use ($request) {
                $query->where('status', 'available');

                if ($request->filled(['check_in', 'check_out'])) {
                    $query->availableBetween(
                        $request->input('check_in'),
                        $request->input('check_out')
                    );
                }

                if ($request->filled('guests')) {
                    $query->where('max_guests', '>=', $request->integer('guests'));
                }

                $query->orderBy('price');
            }])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => __('messages.location_fetched'),
            'data' => new LocationResource($location),
        ]);
    }

    /**
     * Check room availability at a location for a date range.
     *
     * GET /api/v1/locations/{slug}/availability
     *
     * Required query params:
     * - check_in: date (today or later)
     * - check_out: date (after check_in)
     *
     * Optional query params:
     * - guests: int (min capacity filter)
     */
    public function availability(string $slug, LocationAvailabilityRequest $request): JsonResponse
    {

        $location = Location::where('slug', $slug)->active()->firstOrFail();

        $availableRooms = $location->rooms()
            ->availableBetween($request->input('check_in'), $request->input('check_out'))
            ->when($request->filled('guests'), fn ($q) => $q->where('max_guests', '>=', $request->integer('guests'))
            )
            ->orderBy('price')
            ->get();

        return response()->json([
            'success' => true,
            'message' => __('messages.availability_checked'),
            'data' => [
                'location' => new LocationResource($location),
                'available_rooms' => RoomResource::collection($availableRooms),
                'total_available' => $availableRooms->count(),
            ],
        ]);
    }
}
