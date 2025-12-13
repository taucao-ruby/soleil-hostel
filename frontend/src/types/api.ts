import { z } from 'zod'

/**
 * ==========================================
 * API RESPONSE SCHEMAS (Zod Validation)
 * ==========================================
 */

// Base API Response
export const ApiResponseSchema = z.object({
  message: z.string().optional(),
  success: z.boolean().optional(),
})

// Room Schema
export const RoomSchema = z.object({
  id: z.number(),
  name: z.string(),
  price: z.number(),
  max_guests: z.number(),
  status: z.enum(['available', 'booked', 'maintenance']),
  description: z.string().optional(),
  image_url: z.string().url().optional(),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
})

export const RoomsResponseSchema = ApiResponseSchema.extend({
  data: z.array(RoomSchema),
})

// User Schema
export const UserSchema = z.object({
  id: z.number(),
  name: z.string(),
  email: z.string().email(),
  email_verified_at: z.string().nullable().optional(),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
})

export const AuthResponseSchema = ApiResponseSchema.extend({
  user: UserSchema,
  csrf_token: z.string(),
  token: z.string().optional(), // Legacy support
})

// Booking Schema
export const BookingSchema = z.object({
  id: z.number(),
  room_id: z.number(),
  user_id: z.number().optional(),
  guest_name: z.string(),
  guest_email: z.string().email(),
  check_in: z.string(),
  check_out: z.string(),
  guests: z.number().optional(),
  status: z.enum(['pending', 'confirmed', 'cancelled', 'completed']).optional(),
  total_price: z.number().optional(),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
})

export const BookingResponseSchema = ApiResponseSchema.extend({
  data: BookingSchema,
})

export const BookingsResponseSchema = ApiResponseSchema.extend({
  data: z.array(BookingSchema),
})

// Review Schema
export const ReviewSchema = z.object({
  id: z.number(),
  user_id: z.number(),
  room_id: z.number().optional(),
  rating: z.number().min(1).max(5),
  content: z.string(),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
})

export const ReviewsResponseSchema = ApiResponseSchema.extend({
  data: z.array(ReviewSchema),
})

/**
 * ==========================================
 * TYPESCRIPT TYPES (Inferred from Zod)
 * ==========================================
 */

export type Room = z.infer<typeof RoomSchema>
export type RoomsResponse = z.infer<typeof RoomsResponseSchema>

export type User = z.infer<typeof UserSchema>
export type AuthResponse = z.infer<typeof AuthResponseSchema>

export type Booking = z.infer<typeof BookingSchema>
export type BookingResponse = z.infer<typeof BookingResponseSchema>
export type BookingsResponse = z.infer<typeof BookingsResponseSchema>

export type Review = z.infer<typeof ReviewSchema>
export type ReviewsResponse = z.infer<typeof ReviewsResponseSchema>

/**
 * ==========================================
 * API ERROR RESPONSE
 * ==========================================
 */

export const ApiErrorSchema = z.object({
  message: z.string(),
  errors: z.record(z.string(), z.array(z.string())).optional(), // Laravel validation errors
  exception: z.string().optional(),
  file: z.string().optional(),
  line: z.number().optional(),
  trace: z.array(z.any()).optional(),
})

export type ApiError = z.infer<typeof ApiErrorSchema>

/**
 * ==========================================
 * VALIDATION HELPERS
 * ==========================================
 */

/**
 * Safely parse API response with Zod schema
 * Returns parsed data or throws validation error
 */
export function validateApiResponse<T>(schema: z.ZodSchema<T>, data: unknown): T {
  try {
    return schema.parse(data)
  } catch (error) {
    if (error instanceof z.ZodError) {
      console.error('[API Validation Error]', error.issues)
      throw new Error(
        `API response validation failed: ${error.issues.map(e => e.message).join(', ')}`
      )
    }
    throw error
  }
}

/**
 * Safely parse with fallback to unknown data
 * Returns parsed data or null if validation fails
 */
export function safeValidateApiResponse<T>(schema: z.ZodSchema<T>, data: unknown): T | null {
  const result = schema.safeParse(data)
  if (result.success) {
    return result.data
  }
  console.warn('[API Validation Warning]', result.error.issues)
  return null
}
