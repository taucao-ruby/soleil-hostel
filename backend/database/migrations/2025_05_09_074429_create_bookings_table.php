<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ⚠️ CRITICAL: Không dùng foreign key constraints trong test migrations
     * Foreign key + parallel test = SQLSTATE[HY000] 1824 "Failed to open referenced table"
     * Nguyên nhân: Khi chạy --parallel, process A tạo bảng rooms trong transaction,
     *              process B tạo bảng bookings + add constraint FK → bảng rooms chưa commit
     *              → FK constraint không thể tìm thấy bảng rooms → crash
     * 
     * Solution: Dùng unsignedBigInteger + index thay vì foreignId()->constrained()
     * Application-level constraints được enforce tại controller via policy/authorization
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            // 2025 rồi mà còn để foreign key constraint trong test = không đáng làm dev
            // Dùng unsignedBigInteger + index thay vì foreignId()->constrained()
            $table->unsignedBigInteger('room_id')->index();
            $table->date('check_in');
            $table->date('check_out');
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
}; 