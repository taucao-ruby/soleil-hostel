<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_recovery_cases', function (Blueprint $table) {
            $table->timestampTz('escalated_at')->nullable()->after('opened_at');
            $table->index('escalated_at', 'idx_src_escalated_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_recovery_cases', function (Blueprint $table) {
            $table->dropIndex('idx_src_escalated_at');
            $table->dropColumn('escalated_at');
        });
    }
};
