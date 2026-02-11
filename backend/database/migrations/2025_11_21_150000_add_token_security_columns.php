<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRITICAL SECURITY: Thêm cột cho token authentication qua httpOnly cookie
     * - token_identifier: UUID dùng để revoke token (không phải hash)
     * - token_hash: hash của identifier dùng lookup nhanh
     *
     * Lý do: httpOnly cookie chứa token_identifier, backend kiểm tra hash
     * Khi logout → xóa row từ DB → token tự động invalid
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // token_identifier: UUID unique, không phải hash (để logout)
            if (! Schema::hasColumn('personal_access_tokens', 'token_identifier')) {
                $table->uuid('token_identifier')->nullable()->after('token')->unique();
            }

            // token_hash: hash(token_identifier) dùng lookup
            if (! Schema::hasColumn('personal_access_tokens', 'token_hash')) {
                $table->string('token_hash', 255)->nullable()->after('token_identifier');
                $table->index('token_hash');
            }

            // last_rotated_at: Theo dõi khi token được rotate (cho rotating refresh token)
            if (! Schema::hasColumn('personal_access_tokens', 'last_rotated_at')) {
                $table->timestamp('last_rotated_at')->nullable()->after('last_used_at');
            }

            // device_fingerprint: Bind token với device (PHÒNG CHỐNG token theft)
            if (! Schema::hasColumn('personal_access_tokens', 'device_fingerprint')) {
                $table->string('device_fingerprint')->nullable()->after('device_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropUnique(['token_identifier']);
            $table->dropIndex(['token_hash']);
            $table->dropColumn([
                'token_identifier',
                'token_hash',
                'last_rotated_at',
                'device_fingerprint',
            ]);
        });
    }
};
