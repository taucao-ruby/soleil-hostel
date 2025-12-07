<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Unit test base class - no database refresh
 * This avoids the RefreshDatabase trait which triggers Mockery issues
 */
abstract class UnitTestCase extends BaseTestCase
{
    // Intentionally not using RefreshDatabase trait
}
