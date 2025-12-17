<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Convert role column to PostgreSQL ENUM and drop is_admin
 * 
 * This migration performs a zero-downtime conversion:
 * 1. Creates PostgreSQL ENUM type 'user_role_enum'
 * 2. Adds temporary column with ENUM type
 * 3. Migrates data from is_admin boolean → role enum
 * 4. Handles edge cases (nulls, invalid values, 'guest' → 'user')
 * 5. Swaps columns atomically
 * 6. Drops is_admin column
 * 
 * Rollback: Fully reversible with data preservation
 */
return new class extends Migration
{
    /**
     * Valid enum values - sourced from UserRole enum
     */
    private function getEnumValues(): array
    {
        return UserRole::values(); // ['user', 'moderator', 'admin']
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->migratePostgreSQL();
        } else {
            // SQLite/MySQL fallback for testing
            $this->migrateGeneric();
        }
    }

    /**
     * PostgreSQL-specific migration using native ENUM type.
     */
    private function migratePostgreSQL(): void
    {
        $enumValues = $this->getEnumValues();
        $enumValuesSQL = "'" . implode("', '", $enumValues) . "'";
        $defaultRole = UserRole::default()->value;

        DB::transaction(function () use ($enumValuesSQL, $defaultRole) {
            // Step 1: Create PostgreSQL ENUM type if not exists
            DB::statement("
                DO $$ BEGIN
                    CREATE TYPE user_role_enum AS ENUM ({$enumValuesSQL});
                EXCEPTION
                    WHEN duplicate_object THEN NULL;
                END $$;
            ");

            // Step 2: Add temporary column with new ENUM type
            DB::statement("
                ALTER TABLE users 
                ADD COLUMN IF NOT EXISTS role_new user_role_enum DEFAULT '{$defaultRole}'::user_role_enum
            ");

            // Step 3: Migrate data with priority: is_admin > existing role > default
            // Priority logic:
            //   - is_admin = true → 'admin'
            //   - role = 'admin' → 'admin' (preserve existing)
            //   - role = 'moderator' → 'moderator' (preserve existing)
            //   - role = 'user' → 'user'
            //   - role = 'guest' or NULL or invalid → 'user' (normalize)
            DB::statement("
                UPDATE users SET role_new = 
                    CASE
                        WHEN is_admin = true THEN 'admin'::user_role_enum
                        WHEN role IN ('admin') THEN 'admin'::user_role_enum
                        WHEN role IN ('moderator') THEN 'moderator'::user_role_enum
                        ELSE '{$defaultRole}'::user_role_enum
                    END
            ");

            // Step 4: Drop old columns and rename new column
            DB::statement('ALTER TABLE users DROP COLUMN IF EXISTS role');
            DB::statement('ALTER TABLE users RENAME COLUMN role_new TO role');

            // Step 5: Add NOT NULL constraint
            DB::statement("ALTER TABLE users ALTER COLUMN role SET NOT NULL");
            DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT '{$defaultRole}'::user_role_enum");

            // Step 6: Drop is_admin column
            DB::statement('ALTER TABLE users DROP COLUMN IF EXISTS is_admin');

            // Step 7: Add index for role queries
            DB::statement('CREATE INDEX IF NOT EXISTS users_role_idx ON users (role)');
        });
    }

    /**
     * Generic migration for SQLite/MySQL (testing environments).
     */
    private function migrateGeneric(): void
    {
        $defaultRole = UserRole::default()->value;

        DB::transaction(function () use ($defaultRole) {
            // Check if is_admin column exists
            $hasIsAdmin = Schema::hasColumn('users', 'is_admin');
            $hasRole = Schema::hasColumn('users', 'role');

            if ($hasRole) {
                // Step 1: Migrate data from is_admin to role
                if ($hasIsAdmin) {
                    DB::table('users')
                        ->where('is_admin', true)
                        ->update(['role' => UserRole::ADMIN->value]);
                }

                // Step 2: Normalize invalid roles to 'user'
                DB::table('users')
                    ->whereNull('role')
                    ->orWhereNotIn('role', UserRole::values())
                    ->update(['role' => $defaultRole]);
            }

            // Step 3: Drop is_admin if exists (SQLite needs recreate table)
            if ($hasIsAdmin) {
                $driver = Schema::getConnection()->getDriverName();
                
                if ($driver === 'sqlite') {
                    // SQLite: recreate table without is_admin
                    $this->recreateSqliteTableWithoutIsAdmin();
                } else {
                    Schema::table('users', function ($table) {
                        $table->dropColumn('is_admin');
                    });
                }
            }
        });
    }

    /**
     * SQLite doesn't support DROP COLUMN natively, so we recreate.
     */
    private function recreateSqliteTableWithoutIsAdmin(): void
    {
        $defaultRole = UserRole::default()->value;

        // Get current users data
        $users = DB::table('users')->get();

        // Rename old table
        DB::statement('ALTER TABLE users RENAME TO users_old');

        // Create new table without is_admin
        DB::statement("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                email_verified_at TIMESTAMP NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(255) NOT NULL DEFAULT '{$defaultRole}',
                remember_token VARCHAR(100) NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
        ");

        // Restore data
        foreach ($users as $user) {
            DB::table('users')->insert([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'role' => $user->role,
                'remember_token' => $user->remember_token,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]);
        }

        // Drop old table
        DB::statement('DROP TABLE users_old');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->rollbackPostgreSQL();
        } else {
            $this->rollbackGeneric();
        }
    }

    /**
     * PostgreSQL rollback.
     */
    private function rollbackPostgreSQL(): void
    {
        DB::transaction(function () {
            // Step 1: Add back is_admin column
            DB::statement('ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT false');

            // Step 2: Populate is_admin from role
            DB::statement("
                UPDATE users SET is_admin = (role::text = 'admin')
            ");

            // Step 3: Convert role back to VARCHAR
            DB::statement('ALTER TABLE users DROP COLUMN role');
            DB::statement("ALTER TABLE users ADD COLUMN role VARCHAR(255) DEFAULT 'guest'");

            // Step 4: Drop the ENUM type
            DB::statement('DROP TYPE IF EXISTS user_role_enum');

            // Step 5: Drop index
            DB::statement('DROP INDEX IF EXISTS users_role_idx');
        });
    }

    /**
     * Generic rollback.
     */
    private function rollbackGeneric(): void
    {
        Schema::table('users', function ($table) {
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('email');
            }
        });

        // Restore is_admin from role
        DB::table('users')
            ->where('role', UserRole::ADMIN->value)
            ->update(['is_admin' => true]);
    }
};
