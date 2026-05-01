<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_audit_logs', 'actor_email')) {
                $table->string('actor_email', 255)->nullable()->after('actor_id');
            }
            if (! Schema::hasColumn('admin_audit_logs', 'actor_role')) {
                $table->string('actor_role', 50)->nullable()->after('actor_email');
            }
            if (! Schema::hasColumn('admin_audit_logs', 'actor_display_name')) {
                $table->string('actor_display_name', 255)->nullable()->after('actor_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('admin_audit_logs', 'actor_display_name')) {
                $table->dropColumn('actor_display_name');
            }
            if (Schema::hasColumn('admin_audit_logs', 'actor_role')) {
                $table->dropColumn('actor_role');
            }
            if (Schema::hasColumn('admin_audit_logs', 'actor_email')) {
                $table->dropColumn('actor_email');
            }
        });
    }
};
