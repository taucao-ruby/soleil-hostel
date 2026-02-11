<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Thêm các index tối ưu cho pessimistic locking (SELECT FOR UPDATE)
     * Các index này được thiết kế để tối ưu query tìm overlap bookings với locking
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Index chính: room_id + status (active bookings của phòng)
            // Dùng cho SELECT ... FOR UPDATE khi tìm booking trùng
            $table->index(['room_id', 'status'], 'idx_room_active_bookings');

            // Index composite: (room_id, check_in, check_out) cho query overlap
            // Giúp optimizer nhanh chóng tìm các booking có ngày trùng
            $table->index(['room_id', 'check_in', 'check_out'], 'idx_room_dates_overlap');

            // Index riêng cho check_in/check_out để support range queries
            $table->index('check_in', 'idx_check_in');
            $table->index('check_out', 'idx_check_out');
        });

        // Drop constraint cũ nếu tồn tại (vì nó không đủ an toàn cho concurrent environment)
        Schema::table('bookings', function (Blueprint $table) {
            try {
                $table->dropUnique('unique_room_dates');
            } catch (\Exception $e) {
                // Constraint này có thể không tồn tại, bỏ qua
            }
        });

        // Thêm cột status_active (helper cho partial index ở PostgreSQL)
        // MySQL: dùng virtual column nếu cần, hoặc generated column
        // Hiện tại chỉ cần status column đã có, logic check sẽ ở application layer
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_room_active_bookings');
            $table->dropIndex('idx_room_dates_overlap');
            $table->dropIndex('idx_check_in');
            $table->dropIndex('idx_check_out');
        });
    }
};
