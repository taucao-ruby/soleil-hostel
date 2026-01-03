# API Response Wrapper – Usage Guide

> **Purpose**: Standardize all API responses for consistency across frontend, mobile, and OTA integrations.  
> **Location**: `app/Http/Responses/ApiResponse.php`

---

## Response Structure

All API responses follow this format:

```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message",
  "errors": null,
  "meta": {
    "pagination": null
  },
  "timestamp": "2026-01-03T12:00:00+00:00"
}
```

---

## Available Methods

| Method                                           | HTTP Status | Use Case           |
| ------------------------------------------------ | ----------- | ------------------ |
| `ApiResponse::success($data, $message, $status)` | 200         | Standard success   |
| `ApiResponse::created($data, $message)`          | 201         | Resource created   |
| `ApiResponse::noContent()`                       | 204         | Successful DELETE  |
| `ApiResponse::error($message, $errors, $status)` | 400         | Generic error      |
| `ApiResponse::validationErrors($validator)`      | 422         | Validation failed  |
| `ApiResponse::paginated($paginator, $dataKey)`   | 200         | Paginated list     |
| `ApiResponse::notFound($message)`                | 404         | Resource not found |
| `ApiResponse::unauthorized($message)`            | 401         | Not authenticated  |
| `ApiResponse::forbidden($message)`               | 403         | Not authorized     |
| `ApiResponse::serverError($message)`             | 500         | Server error       |

---

## Controller Usage Examples

### Import Statement

```php
use App\Http\Responses\ApiResponse;
```

---

### Standard Success Response

**Before:**

```php
public function show(Room $room)
{
    return response()->json(['room' => $room], 200);
}
```

**After:**

```php
public function show(Room $room)
{
    return ApiResponse::success($room);
}
```

**Output:**

```json
{
  "success": true,
  "data": { "id": 1, "name": "Deluxe Suite", ... },
  "message": null,
  "errors": null,
  "meta": { "pagination": null },
  "timestamp": "2026-01-03T12:00:00+00:00"
}
```

---

### Created Response (201)

**Before:**

```php
public function store(Request $request)
{
    $booking = Booking::create($request->validated());
    return response()->json(['booking' => $booking], 201);
}
```

**After:**

```php
public function store(Request $request)
{
    $booking = Booking::create($request->validated());
    return ApiResponse::created($booking, 'Booking confirmed.');
}
```

**Output:**

```json
{
  "success": true,
  "data": { "id": 42, "guest_name": "John Doe", ... },
  "message": "Booking confirmed.",
  "errors": null,
  "meta": { "pagination": null },
  "timestamp": "2026-01-03T12:00:00+00:00"
}
```

---

### Validation Error Response (422)

**Before:**

```php
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'check_in' => 'required|date',
        'check_out' => 'required|date|after:check_in',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }
    // ...
}
```

**After:**

```php
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'check_in' => 'required|date',
        'check_out' => 'required|date|after:check_in',
    ]);

    if ($validator->fails()) {
        return ApiResponse::validationErrors($validator);
    }
    // ...
}
```

**Output:**

```json
{
  "success": false,
  "data": null,
  "message": "Validation failed.",
  "errors": {
    "check_in": ["The check in field is required."],
    "check_out": ["The check out must be after check in."]
  },
  "meta": { "pagination": null },
  "timestamp": "2026-01-03T12:00:00+00:00"
}
```

> **Note**: If using Form Requests, validation exceptions are automatically wrapped via exception handler.

---

### Paginated Response

**Before:**

```php
public function index()
{
    $rooms = Room::paginate(15);
    return response()->json($rooms);
}
```

**After:**

```php
public function index()
{
    $rooms = Room::paginate(15);
    return ApiResponse::paginated($rooms, 'rooms');
}
```

**Output:**

```json
{
  "success": true,
  "data": {
    "rooms": [
      { "id": 1, "name": "Single Room" },
      { "id": 2, "name": "Double Room" }
    ]
  },
  "message": null,
  "errors": null,
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 10,
      "per_page": 15,
      "total": 150,
      "from": 1,
      "to": 15
    }
  },
  "timestamp": "2026-01-03T12:00:00+00:00"
}
```

---

### Delete Response (204)

**Before:**

```php
public function destroy(Room $room)
{
    $room->delete();
    return response()->json(null, 204);
}
```

**After:**

```php
public function destroy(Room $room)
{
    $room->delete();
    return ApiResponse::noContent();
}
```

---

### Error Response

```php
public function processPayment(Request $request)
{
    if (!$this->paymentGateway->isAvailable()) {
        return ApiResponse::error('Payment gateway unavailable.', null, 503);
    }
    // ...
}
```

---

### Not Found Response

```php
public function show(int $id)
{
    $booking = Booking::find($id);

    if (!$booking) {
        return ApiResponse::notFound('Booking not found.');
    }

    return ApiResponse::success($booking);
}
```

---

## Exception Handler Integration

The following exceptions are automatically wrapped (for API requests only):

| Exception                 | Response                   |
| ------------------------- | -------------------------- |
| `ValidationException`     | 422 with validation errors |
| `ModelNotFoundException`  | 404 "Model not found"      |
| `NotFoundHttpException`   | 404 "Endpoint not found"   |
| `AuthenticationException` | 401 "Unauthenticated"      |
| `AuthorizationException`  | 403 "Unauthorized"         |
| `OptimisticLockException` | 409 "Resource out of date" |

---

## Migration Plan

### Phase 1: New Endpoints (Immediate)

All new controller methods must use `ApiResponse`.

### Phase 2: Critical Endpoints (Week 1)

Update booking-related controllers:

- `BookingController`
- `AvailabilityController`
- `PaymentController`

### Phase 3: Remaining Endpoints (Gradual)

Update other controllers as they're touched for bug fixes or features.

### Search Pattern

Find all raw JSON responses to migrate:

```bash
grep -rn "response()->json" app/Http/Controllers/
```

---

## Verification Checklist

After implementation, verify:

- [ ] `php artisan test` passes
- [ ] Health check endpoint works: `GET /api/health`
- [ ] Booking creation returns 201 with standard structure
- [ ] Validation errors return 422 with `errors` object
- [ ] Paginated endpoints include `meta.pagination`
- [ ] 404 responses follow standard format
- [ ] 401/403 responses follow standard format
- [ ] Frontend can parse all responses consistently

---

## Quick Reference

```php
use App\Http\Responses\ApiResponse;

// Success
return ApiResponse::success($data);
return ApiResponse::success($data, 'Operation completed.');

// Created
return ApiResponse::created($resource);
return ApiResponse::created($resource, 'Booking confirmed.');

// No Content (DELETE)
return ApiResponse::noContent();

// Errors
return ApiResponse::error('Something went wrong.');
return ApiResponse::error('Bad request.', ['field' => ['Error detail.']], 400);
return ApiResponse::notFound('Booking not found.');
return ApiResponse::unauthorized();
return ApiResponse::forbidden();
return ApiResponse::serverError();

// Validation
return ApiResponse::validationErrors($validator);
return ApiResponse::validationErrors($validator->errors());
return ApiResponse::validationErrors(['field' => ['Error message.']]);  // raw array

// Pagination
return ApiResponse::paginated($paginator);
return ApiResponse::paginated($paginator, 'bookings');
```
