<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'number_of_guests')) {
                $table->unsignedSmallInteger('number_of_guests')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'special_requests')) {
                $table->text('special_requests')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'special_requests')) {
                $table->dropColumn('special_requests');
            }

            if (Schema::hasColumn('bookings', 'number_of_guests')) {
                $table->dropColumn('number_of_guests');
            }
        });
    }
};
