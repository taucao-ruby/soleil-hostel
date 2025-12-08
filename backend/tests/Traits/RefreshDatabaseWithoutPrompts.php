<?php

namespace Tests\Traits;

use Illuminate\Foundation\Testing\RefreshDatabase as BaseRefreshDatabase;

/**
 * Custom RefreshDatabase trait that suppresses all prompts
 */
trait RefreshDatabaseWithoutPrompts
{
    use BaseRefreshDatabase {
        migrateDatabases as parentMigrateDatabases;
    }

    /**
     * Migrate the database with --no-interaction flag
     */
    protected function migrateDatabases()
    {
        // Call parent migration with no interaction
        $this->artisan('migrate:fresh', array_merge(
            $this->migrateFreshUsing(),
            ['--no-interaction' => true]
        ));
    }
}
