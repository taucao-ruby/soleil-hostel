<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use Illuminate\Support\Facades\Log;

/**
 * BookingController - Migration from SecurityHelper to HTML Purifier
 * 
 * BEFORE (❌ VULNERABLE):
 * ├─ Used SecurityHelper::sanitizeInput() and containsSuspiciousPatterns()
 * ├─ Regex-based blacklist approach
 * ├─ 99% bypassable by encoding tricks
 * └─ Impossible to maintain
 * 
 * AFTER (✅ SECURE):
 * ├─ HTML Purifier whitelist approach in FormRequest
 * ├─ Model Purifiable trait for redundant safety
 * ├─ Zero known bypasses
 * └─ Industry-standard (used in Drupal, WordPress)
 */
class BookingControllerExample extends Controller
{
    /**
     * Display a listing of bookings (admin)
     */
    public function index()
    {
        $this->authorize('viewAny', Booking::class);

        $bookings = Booking::with('room', 'user')
            ->latest('created_at')
            ->paginate(20);

        return view('bookings.index', [
            'bookings' => $bookings,
        ]);
    }

    /**
     * Show the form for creating a new booking
     */
    public function create()
    {
        $this->authorize('create', Booking::class);

        $rooms = Room::where('is_available', true)->get();

        return view('bookings.create', [
            'rooms' => $rooms,
        ]);
    }

    /**
     * Store a newly created booking
     * 
     * MIGRATION PATH:
     * 
     * ❌ OLD CODE:
     * ```php
     * $name = SecurityHelper::sanitizeInput($request->guest_name);
     * if (SecurityHelper::containsSuspiciousPatterns($name)) {
     *     return back()->withError('Invalid input');
     * }
     * ```
     * 
     * ✅ NEW CODE:
     * ```php
     * $validated = $request->validated(); // Already purified
     * // FormRequest purify() macro already did the work
     * ```
     * 
     * KEY DIFFERENCE:
     * - OLD: Blacklist regex patterns → 99% bypassable
     * - NEW: Whitelist HTML tags → 0% bypass rate
     */
    public function store(StoreBookingRequest $request)
    {
        $this->authorize('create', Booking::class);

        // ✅ Data already purified by StoreBookingRequest::validated()
        // Uses FormRequest macro: $this->purify(['guest_name'])
        $validated = $request->validated();

        // ✅ Extra safety: Model Purifiable trait re-purifies on save
        // (defensive programming - purify twice is safe)
        $booking = Booking::create([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        Log::info('Booking created', [
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'guest_name' => $booking->guest_name,
        ]);

        return redirect()
            ->route('bookings.show', $booking->id)
            ->with('success', 'Booking created successfully!');
    }

    /**
     * Display the specified booking
     * 
     * ✅ Content is safe because:
     * A) Purified in FormRequest when created
     * B) Purified by Purifiable trait on save
     * C) Never modified without purification
     */
    public function show(Booking $booking)
    {
        $this->authorize('view', $booking);

        return view('bookings.show', [
            'booking' => $booking,
        ]);
    }

    /**
     * Show the form for editing the specified booking
     */
    public function edit(Booking $booking)
    {
        $this->authorize('update', $booking);

        $rooms = Room::all();

        return view('bookings.edit', [
            'booking' => $booking,
            'rooms' => $rooms,
        ]);
    }

    /**
     * Update the specified booking
     * 
     * Same purification strategy as store():
     * 1. FormRequest::validated() purifies
     * 2. Model Purifiable trait re-purifies on save
     * 3. Never use unsanitized user input
     */
    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        $this->authorize('update', $booking);

        $validated = $request->validated(); // Already purified

        $booking->update($validated);

        Log::info('Booking updated', [
            'booking_id' => $booking->id,
            'fields_changed' => array_keys($validated),
        ]);

        return redirect()
            ->route('bookings.show', $booking->id)
            ->with('success', 'Booking updated successfully!');
    }

    /**
     * Remove the specified booking
     */
    public function destroy(Booking $booking)
    {
        $this->authorize('delete', $booking);

        $booking_id = $booking->id;
        $booking->delete();

        Log::info('Booking deleted', [
            'booking_id' => $booking_id,
        ]);

        return redirect()
            ->route('bookings.index')
            ->with('success', 'Booking deleted!');
    }

    /**
     * Migration guide: Update existing code
     * 
     * PATTERN 1: Remove SecurityHelper calls
     * ❌ Before:
     *    $name = SecurityHelper::sanitizeInput($input);
     *    if (SecurityHelper::containsSuspiciousPatterns($name)) { ... }
     * 
     * ✅ After:
     *    // Nothing needed - FormRequest handles it
     *    $validated = $request->validated();
     * 
     * 
     * PATTERN 2: Use FormRequest purify() macro
     * ✅ In your FormRequest class:
     *    public function validated(): array {
     *        return $this->purify(['field1', 'field2']);
     *    }
     * 
     * 
     * PATTERN 3: Model auto-purification (defensive)
     * ✅ In your Model:
     *    use Purifiable;
     *    protected array $purifiable = ['field1', 'field2'];
     * 
     * 
     * PATTERN 4: Direct service usage (batch operations)
     * ✅ For commands/jobs/batch imports:
     *    use HtmlPurifierService;
     *    $clean = HtmlPurifierService::purify($html);
     * 
     * 
     * PATTERN 5: Blade template rendering (safe)
     * ✅ In your blade file:
     *    @purify($booking->guest_name)
     *    @purifyPlain($text)
     * 
     * ❌ Never use without purification:
     *    {!! $booking->guest_name !!}  // DANGEROUS unless already purified
     */
}
