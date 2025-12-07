<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected $withoutMiddleware = [
        \App\Http\Middleware\VerifyCsrfToken::class,
    ];

    // Disable confirmation prompts during testing
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock console output to prevent confirmation prompts
        $this->mockConsoleOutput();
        
        // Disable all interactive prompts
        if (class_exists(\Laravel\Prompts\Prompt::class)) {
            \Laravel\Prompts\Prompt::preventInteraction();
        }
    }

    /**
     * Mock console output to prevent Mockery confirmation issues
     */
    protected function mockConsoleOutput(): void
    {
        try {
            $mockConsole = Mockery::mock('overload:Symfony\Component\Console\Style\SymfonyStyle')
                ->shouldReceive('askQuestion')
                ->andReturnNull()
                ->getMock();
        } catch (\Exception $e) {
            // Already mocked or not available
        }
    }

    protected function disableExceptionHandling()
    {
        $this->withoutExceptionHandling();
    }
}
