<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    /**
     * Get paginated customer list aggregated from bookings table.
     */
    public function getCustomers(int $perPage = 15, ?string $search = null)
    {
        $query = DB::table('bookings')
            ->select(
                'guest_email as email',
                DB::raw('MAX(guest_name) as name'),
                DB::raw('COUNT(id) as total_stays'),
                DB::raw('SUM(amount) as total_spent'),
                DB::raw('MAX(created_at) as last_visit'),
                DB::raw('MIN(created_at) as first_visit')
            )
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->groupBy('guest_email');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('guest_name', 'ilike', '%'.$search.'%')
                    ->orWhere('guest_email', 'ilike', '%'.$search.'%');
            });
        }

        // We use paginate() directly on query builder.
        // Note: For large datasets, a dedicated users table or materialized view is better.
        return $query->orderBy('last_visit', 'desc')->paginate($perPage);
    }

    /**
     * Get detailed profile for a single customer by email.
     */
    public function getCustomerProfile(string $email)
    {
        $cacheKey = "customer_profile_{$email}";

        return Cache::remember($cacheKey, 300, function () use ($email) {
            $stats = DB::table('bookings')
                ->select(
                    'guest_email as email',
                    DB::raw('MAX(guest_name) as name'),
                    DB::raw('COUNT(id) as total_stays'),
                    DB::raw('SUM(amount) as total_spent'),
                    DB::raw('SUM(check_out - check_in) as total_nights'),
                    DB::raw('MAX(created_at) as last_visit'),
                    DB::raw('MIN(created_at) as first_visit')
                )
                ->where('guest_email', $email)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'cancelled')
                ->groupBy('guest_email')
                ->first();

            if (! $stats) {
                return null;
            }

            // Find preferred location
            $preferredLocation = DB::table('bookings')
                ->join('locations', 'bookings.location_id', '=', 'locations.id')
                ->select('locations.name', DB::raw('COUNT(bookings.id) as stays'))
                ->where('bookings.guest_email', $email)
                ->whereNull('bookings.deleted_at')
                ->groupBy('locations.id', 'locations.name')
                ->orderBy('stays', 'desc')
                ->first();

            // Find average rating given
            $avgRating = DB::table('reviews')
                ->where('guest_email', $email)
                ->avg('rating');

            $stats->preferred_location = $preferredLocation ? $preferredLocation->name : null;
            $stats->average_rating = $avgRating ? round($avgRating, 1) : null;

            return $stats;
        });
    }

    /**
     * Get all stays (bookings) for a customer by email.
     */
    public function getCustomerBookings(string $email)
    {
        return Booking::with(['room' => function ($q) {
            // Select specific columns to reduce data transfer payload size
            $q->select('id', 'name', 'room_number', 'location_id')->with('location:id,name');
        }])
            ->where('guest_email', $email)
            ->orderBy('check_in', 'desc')
            ->get();
    }

    /**
     * Get system-wide customer stats.
     */
    public function getAggregateStats()
    {
        return Cache::remember('customer_aggregate_stats', 300, function () {
            $totalCustomers = DB::table('bookings')
                ->distinct('guest_email')
                ->whereNull('deleted_at')
                ->where('status', '!=', 'cancelled')
                ->count('guest_email');

            $totalRevenue = DB::table('bookings')
                ->whereNull('deleted_at')
                ->where('status', '!=', 'cancelled')
                ->sum('amount');

            $returningCustomers = DB::table(function ($query) {
                $query->select('guest_email')
                    ->from('bookings')
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'cancelled')
                    ->groupBy('guest_email')
                    ->havingRaw('COUNT(id) > 1');
            }, 'returning')->count();

            return [
                'total_customers' => $totalCustomers,
                'total_revenue' => $totalRevenue,
                'returning_customers' => $returningCustomers,
                'return_rate' => $totalCustomers > 0 ? round(($returningCustomers / $totalCustomers) * 100, 1) : 0,
            ];
        });
    }
}
