<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Validator;

/**
 * Standardized API response wrapper for Soleil Hostel.
 *
 * Usage:
 *   return ApiResponse::success($data);
 *   return ApiResponse::created($booking);
 *   return ApiResponse::error('Something went wrong', null, 500);
 *   return ApiResponse::validationErrors($validator);
 *   return ApiResponse::paginated($paginator);
 */
final class ApiResponse
{
    /**
     * Success response.
     *
     * @param mixed $data Response payload
     * @param string|null $message Human-readable message
     * @param int $status HTTP status code
     */
    public static function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return self::respond(
            success: true,
            data: $data,
            message: $message,
            errors: null,
            meta: null,
            status: $status
        );
    }

    /**
     * Created response (201).
     *
     * @param mixed $data Created resource
     * @param string|null $message Human-readable message
     */
    public static function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::respond(
            success: true,
            data: $data,
            message: $message ?? 'Resource created successfully.',
            errors: null,
            meta: null,
            status: 201
        );
    }

    /**
     * No content response (204).
     * Used for successful DELETE operations.
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Error response.
     *
     * @param string $message Error message
     * @param array|null $errors Detailed errors
     * @param int $status HTTP status code
     */
    public static function error(string $message, ?array $errors = null, int $status = 400): JsonResponse
    {
        return self::respond(
            success: false,
            data: null,
            message: $message,
            errors: $errors,
            meta: null,
            status: $status
        );
    }

    /**
     * Validation error response (422).
     * Accepts Laravel Validator, MessageBag, or raw errors array.
     *
     * @param Validator|MessageBag|array $validatorOrBag
     */
    public static function validationErrors(Validator|MessageBag|array $validatorOrBag): JsonResponse
    {
        if (is_array($validatorOrBag)) {
            $errors = $validatorOrBag;
        } elseif ($validatorOrBag instanceof Validator) {
            $errors = $validatorOrBag->errors()->toArray();
        } else {
            $errors = $validatorOrBag->toArray();
        }

        return self::respond(
            success: false,
            data: null,
            message: 'Validation failed.',
            errors: $errors,
            meta: null,
            status: 422
        );
    }

    /**
     * Paginated response.
     * Extracts pagination metadata without transforming collection items.
     *
     * @param LengthAwarePaginator $paginator
     * @param string $dataKey Key name for the items array
     */
    public static function paginated(LengthAwarePaginator $paginator, string $dataKey = 'items'): JsonResponse
    {
        $meta = [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];

        return self::respond(
            success: true,
            data: [$dataKey => $paginator->items()],
            message: null,
            errors: null,
            meta: $meta,
            status: 200
        );
    }

    /**
     * Not found response (404).
     *
     * @param string $message
     */
    public static function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return self::error($message, null, 404);
    }

    /**
     * Unauthorized response (401).
     *
     * @param string $message
     */
    public static function unauthorized(string $message = 'Unauthorized.'): JsonResponse
    {
        return self::error($message, null, 401);
    }

    /**
     * Forbidden response (403).
     *
     * @param string $message
     */
    public static function forbidden(string $message = 'Forbidden.'): JsonResponse
    {
        return self::error($message, null, 403);
    }

    /**
     * Server error response (500).
     *
     * @param string $message
     */
    public static function serverError(string $message = 'Internal server error.'): JsonResponse
    {
        return self::error($message, null, 500);
    }

    /**
     * Build the standardized response structure.
     */
    private static function respond(
        bool $success,
        mixed $data,
        ?string $message,
        ?array $errors,
        ?array $meta,
        int $status
    ): JsonResponse {
        $response = [
            'success' => $success,
            'data' => $data,
            'message' => $message,
            'errors' => $errors,
            'meta' => $meta ?? ['pagination' => null],
            'timestamp' => now()->toIso8601String(),
        ];

        return response()->json($response, $status);
    }
}
