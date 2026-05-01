<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 4 / 3F: preserve the AI proposal audit trail when a user is deleted.
 *
 * Before: user_id FK uses ON DELETE CASCADE → deleting a user nukes every
 * proposal event for that user, destroying the audit trail.
 *
 * After: ON DELETE SET NULL + denormalised actor_email / actor_role /
 * actor_display_name columns populated at write time so investigators can
 * still answer "who did what" after the user record is gone.
 *
 * One-way migration: rolling back only restores the cascade, it cannot
 * resurrect rows already cascaded away. Acceptable — this is a tightening.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_proposal_events', function (Blueprint $table) {
            // Denormalised actor identity. Populated by the application at write
            // time; survives user deletion so the audit row remains attributable.
            if (! Schema::hasColumn('ai_proposal_events', 'actor_email')) {
                $table->string('actor_email', 255)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('ai_proposal_events', 'actor_role')) {
                $table->string('actor_role', 32)->nullable()->after('actor_email');
            }
            if (! Schema::hasColumn('ai_proposal_events', 'actor_display_name')) {
                $table->string('actor_display_name', 255)->nullable()->after('actor_role');
            }
        });

        // Drop and recreate the FK with nullOnDelete. Doctrine DBAL is not in
        // composer here; use raw SQL for the constraint swap. Postgres-only.
        DB::statement('ALTER TABLE ai_proposal_events DROP CONSTRAINT IF EXISTS ai_proposal_events_user_id_foreign');
        DB::statement('ALTER TABLE ai_proposal_events ALTER COLUMN user_id DROP NOT NULL');
        DB::statement('
            ALTER TABLE ai_proposal_events
            ADD CONSTRAINT ai_proposal_events_user_id_foreign
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ai_proposal_events DROP CONSTRAINT IF EXISTS ai_proposal_events_user_id_foreign');
        DB::statement('
            ALTER TABLE ai_proposal_events
            ADD CONSTRAINT ai_proposal_events_user_id_foreign
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ');

        Schema::table('ai_proposal_events', function (Blueprint $table) {
            if (Schema::hasColumn('ai_proposal_events', 'actor_display_name')) {
                $table->dropColumn('actor_display_name');
            }
            if (Schema::hasColumn('ai_proposal_events', 'actor_role')) {
                $table->dropColumn('actor_role');
            }
            if (Schema::hasColumn('ai_proposal_events', 'actor_email')) {
                $table->dropColumn('actor_email');
            }
        });
    }
};
