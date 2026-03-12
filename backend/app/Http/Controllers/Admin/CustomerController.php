<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService
    ) {}

    /**
     * Display a listing of customers.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $customers = $this->customerService->getCustomers(15, $search);

        return response()->json([
            'success' => true,
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
            ]
        ]);
    }

    /**
     * Display the specified customer.
     */
    public function show(string $email)
    {
        $profile = $this->customerService->getCustomerProfile($email);

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $profile
        ]);
    }

    /**
     * Display bookings for the specified customer.
     */
    public function bookings(string $email)
    {
        $bookings = $this->customerService->getCustomerBookings($email);

        return response()->json([
            'success' => true,
            'data' => $bookings
        ]);
    }

    /**
     * Display aggregate stats.
     */
    public function stats()
    {
        $stats = $this->customerService->getAggregateStats();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
