<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'cancelled_by_email')) {
                $table->string('cancelled_by_email', 255)->nullable()->after('cancelled_by');
            }
            if (! Schema::hasColumn('bookings', 'cancelled_by_role')) {
                $table->string('cancelled_by_role', 50)->nullable()->after('cancelled_by_email');
            }
            if (! Schema::hasColumn('bookings', 'cancelled_by_display')) {
                $table->string('cancelled_by_display', 255)->nullable()->after('cancelled_by_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'cancelled_by_display')) {
                $table->dropColumn('cancelled_by_display');
            }
            if (Schema::hasColumn('bookings', 'cancelled_by_role')) {
                $table->dropColumn('cancelled_by_role');
            }
            if (Schema::hasColumn('bookings', 'cancelled_by_email')) {
                $table->dropColumn('cancelled_by_email');
            }
        });
    }
};
