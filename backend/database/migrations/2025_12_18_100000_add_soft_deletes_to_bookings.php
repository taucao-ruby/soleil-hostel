<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add soft delete columns to bookings table for complete audit trail.
 * 
 * PURPOSE:
 * - Preserve all booking data for audit, compliance (GDPR, SOX), and recovery
 * - Track who deleted bookings and when
 * - Allow admin restoration of accidentally deleted bookings
 * - Maintain referential integrity with related entities (Users, Rooms, Payments)
 * 
 * COLUMNS ADDED:
 * - deleted_at: Timestamp when soft deleted (NULL = active)
 * - deleted_by: User ID who performed the deletion (for audit trail)
 * 
 * INDEXES:
 * - idx_bookings_deleted_at: Fast filtering of active vs deleted records
 * - idx_bookings_soft_delete_audit: Composite for admin audit queries
 * 
 * PERFORMANCE CONSIDERATIONS:
 * - Partial index on NULL deleted_at would be ideal but SQLite doesn't support
 * - Regular index still provides O(log n) lookups
 * - Most queries filter WHERE deleted_at IS NULL (handled by SoftDeletes trait)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Soft delete timestamp - Laravel SoftDeletes trait requires this column
            $table->softDeletes();
            
            // Audit: Track who deleted the booking
            // Uses unsigned big integer for user_id foreign key compatibility
            // Nullable because existing records won't have this, and CASCADE shouldn't delete audit data
            $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            
            // Index for fast filtering active records
            // All standard queries use WHERE deleted_at IS NULL
            $table->index('deleted_at', 'idx_bookings_deleted_at');
            
            // Composite index for admin audit queries
            // Covers: "Show me all bookings deleted by user X" or "Bookings deleted in date range"
            $table->index(['deleted_at', 'deleted_by'], 'idx_bookings_soft_delete_audit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_soft_delete_audit');
            $table->dropIndex('idx_bookings_deleted_at');
            $table->dropColumn(['deleted_at', 'deleted_by']);
        });
    }
};
