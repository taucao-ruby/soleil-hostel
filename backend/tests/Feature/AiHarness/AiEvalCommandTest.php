<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\AiHarness\Exceptions\ProviderTimeoutException;
use App\AiHarness\Exceptions\ProviderUnavailableException;
use App\AiHarness\Providers\ModelProviderInterface;
use App\Console\Commands\AiEvalCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\ThrowingModelProvider;
use Tests\TestCase;

class AiEvalCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_all_phases_gate_is_hermetic_and_rolls_back_empty_database_fixtures(): void
    {
        $this->assertDatabaseCount('users', 0);
        $initialLocationCount = \App\Models\Location::query()->count();
        $initialRoomCount = \App\Models\Room::query()->count();
        $initialBookingCount = \App\Models\Booking::query()->count();
        $initialProposalCount = \App\Models\AiProposal::query()->count();

        $this->artisan('ai:eval', ['--all-phases' => true])
            ->expectsOutputToContain('REGRESSION GATE VERDICT: PASS')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('locations', $initialLocationCount);
        $this->assertDatabaseCount('rooms', $initialRoomCount);
        $this->assertDatabaseCount('bookings', $initialBookingCount);
        $this->assertDatabaseCount('ai_proposals', $initialProposalCount);
    }

    public function test_provider_error_blocks_answer_scenarios(): void
    {
        $this->useEvalProvider(new ThrowingModelProvider(
            new ProviderUnavailableException('ai_eval', 'Forced provider error.'),
        ));

        $this->artisan('ai:eval', ['--phase' => '3', '--dataset' => 'admin_draft'])
            ->expectsOutputToContain('GATE-4 VERDICT: BLOCKED')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_provider_fallback_does_not_satisfy_answer_scenarios(): void
    {
        $this->useEvalProvider(new ThrowingModelProvider(
            new ProviderTimeoutException('ai_eval', 1, 'Forced provider timeout.'),
        ));

        $this->artisan('ai:eval', ['--phase' => '3', '--dataset' => 'admin_draft'])
            ->expectsOutputToContain('GATE-4 VERDICT: BLOCKED')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_provider_exception_blocks_gate(): void
    {
        $this->useEvalProvider(new ThrowingModelProvider(
            new RuntimeException('Forced unexpected provider exception.'),
        ));

        $this->artisan('ai:eval', ['--phase' => '3', '--dataset' => 'admin_draft'])
            ->expectsOutputToContain('AI EVAL VERDICT: BLOCKED')
            ->assertExitCode(Command::FAILURE);
    }

    private function useEvalProvider(ModelProviderInterface $provider): void
    {
        $this->app->instance(AiEvalCommand::MODEL_PROVIDER_BINDING, $provider);
    }
}
