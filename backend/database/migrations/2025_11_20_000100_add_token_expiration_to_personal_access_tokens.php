<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Thêm cột hỗ trợ token expiration + revocation vào bảng personal_access_tokens
     * Critical để prevent token lifetime vô hạn
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // revoked_at: Token bị revoke (logout/refresh/force logout all devices)
            if (!Schema::hasColumn('personal_access_tokens', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('expires_at');
            }
            
            // remember_token_id: Link tới remember token (cho "Remember me" flow)
            if (!Schema::hasColumn('personal_access_tokens', 'remember_token_id')) {
                $table->uuid('remember_token_id')->nullable()->after('revoked_at');
            }
            
            // type: short_lived (1-2h, web SPA) hoặc long_lived (30-90 ngày, mobile + remember)
            if (!Schema::hasColumn('personal_access_tokens', 'type')) {
                $table->string('type')->default('short_lived')->after('remember_token_id');
            }
            
            // device_id: UUID unique per device, dùng cho single device login
            if (!Schema::hasColumn('personal_access_tokens', 'device_id')) {
                $table->uuid('device_id')->nullable()->after('type');
            }
            
            // refresh_count: Đếm lần refresh token, dùng detect suspicious activity
            if (!Schema::hasColumn('personal_access_tokens', 'refresh_count')) {
                $table->integer('refresh_count')->default(0)->after('device_id');
            }
            
            // Tạo indexes cho performance
            $table->index('expires_at');
            $table->index('revoked_at');
            $table->index('device_id');
            $table->index(['tokenable_id', 'tokenable_type', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['revoked_at']);
            $table->dropIndex(['device_id']);
            $table->dropIndex(['tokenable_id', 'tokenable_type', 'type']);
            
            $table->dropColumn([
                'expires_at',
                'revoked_at',
                'remember_token_id',
                'type',
                'device_id',
                'refresh_count',
            ]);
        });
    }
};
