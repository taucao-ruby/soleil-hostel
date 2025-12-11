<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ⚠️ Foreign key constraint + parallel test = tự bắn vào đầu mình 100 lần
     * Thay bằng unsignedBigInteger + index
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Không dùng foreignId()->constrained() vì sẽ crash khi --parallel
            $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
            // Add indexes for better query performance
            $table->index(['room_id', 'check_in', 'check_out']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropIndex(['room_id', 'check_in', 'check_out']);
            $table->dropIndex(['status']);
        });
    }
};
