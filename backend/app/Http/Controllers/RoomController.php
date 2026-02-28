<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRoomsRequest;
use App\Http\Requests\RoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RoomController - Thin controller following clean architecture.
 *
 * Responsibilities:
 * - Validate input (via RoomRequest)
 * - Authorize actions (via Policies)
 * - Delegate business logic to RoomService
 * - Return HTTP responses
 *
 * All business logic (including optimistic locking) lives in RoomService.
 */
class RoomController extends Controller
{
    public function __construct(
        private RoomService $roomService
    ) {}

    /**
     * List all rooms.
     *
     * GET /api/rooms
     *
     * Optional query params:
     * - location_id: int - filter by location
     * - check_in: date - filter available rooms (requires check_out)
     * - check_out: date - filter available rooms (requires check_in)
     */
    public function index(ListRoomsRequest $request): JsonResponse
    {
        $rooms = Room::query()
            ->with('location:id,name,slug')
            ->withCount('activeBookings')
            ->when($request->filled('location_id'), fn ($q) => $q->where('location_id', $request->integer('location_id'))
            )
            ->when($request->filled(['check_in', 'check_out']), fn ($q) => $q->availableBetween($request->input('check_in'), $request->input('check_out'))
            )
            ->orderBy('price')
            ->get();

        return response()->json([
            'success' => true,
            'message' => __('messages.room_list_fetched'),
            'data' => RoomResource::collection($rooms),
        ]);
    }

    /**
     * Show a single room.
     *
     * GET /api/rooms/{room}
     *
     * Response includes lock_version for optimistic locking.
     * Clients should store this version and send it back when updating.
     */
    public function show(Room $room): JsonResponse
    {
        $room = $this->roomService->getRoomById($room->id);

        return response()->json([
            'success' => true,
            'message' => __('messages.room_fetched'),
            'data' => new RoomResource($room),
        ]);
    }

    /**
     * Store a new room.
     *
     * POST /api/rooms
     *
     * New rooms automatically get lock_version = 1.
     */
    public function store(RoomRequest $request): JsonResponse
    {
        // Use policy to check authorization
        $this->authorize('create', Room::class);

        $room = $this->roomService->createRoom($request->validated());

        return response()->json([
            'success' => true,
            'message' => __('messages.room_created'),
            'data' => new RoomResource($room),
        ], 201);
    }

    /**
     * Update a room with optimistic locking.
     *
     * PUT/PATCH /api/rooms/{id}
     *
     * Request body should include:
     * - All room fields to update
     * - lock_version: The version received when the room was last read
     *
     * If lock_version doesn't match the current DB version, returns 409 Conflict.
     * This prevents the "lost update" problem in concurrent systems.
     *
     * Example request:
     * {
     *   "name": "Updated Room Name",
     *   "price": 150.00,
     *   "lock_version": 5
     * }
     *
     * On success, response includes new lock_version.
     * On conflict, client should refresh and retry.
     */
    public function update(RoomRequest $request, Room $room): JsonResponse
    {
        // Use policy to check authorization
        $this->authorize('update', $room);

        // Extract lock_version from validated request
        // If not provided, service will handle backward compatibility
        $lockVersion = $request->getLockVersion();

        // Get validated data excluding lock_version (service handles versioning)
        $data = collect($request->validated())->except(['lock_version'])->toArray();

        // Delegate to service - may throw OptimisticLockException
        $updatedRoom = $this->roomService->updateWithOptimisticLock(
            $room,
            $data,
            $lockVersion
        );

        return response()->json([
            'success' => true,
            'message' => __('messages.room_updated'),
            'data' => new RoomResource($updatedRoom),
        ]);
    }

    /**
     * Delete a room.
     *
     * DELETE /api/rooms/{id}
     *
     * Optionally accepts lock_version in request body for consistency check.
     */
    public function destroy(Request $request, Room $room): JsonResponse
    {
        // Use policy to check authorization
        $this->authorize('delete', $room);

        // Optional: extract lock_version if provided
        $lockVersion = $request->input('lock_version')
            ? (int) $request->input('lock_version')
            : null;

        $this->roomService->deleteWithOptimisticLock($room, $lockVersion);

        return response()->json([
            'success' => true,
            'message' => __('messages.room_deleted'),
        ]);
    }
}
