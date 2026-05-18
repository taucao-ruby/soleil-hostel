<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Middleware;

use App\Queue\Middleware\ThrottlesPerRecipient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * ThrottlesPerRecipientTest — unit tests for the queue middleware that BL-4
 * relies on for delay-not-drop semantics.
 *
 * Critical property under test: when the recipient limiter is exhausted, the
 * middleware MUST call $job->release($availableIn) instead of letting $next
 * proceed (which would deliver the email). Anything else regresses BL-4.
 */
class ThrottlesPerRecipientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Each test owns its own limiter registrations; clear any prior state.
        // RateLimiter facade in unit tests resolves against the array cache
        // store provided by the application container — we boot a minimal
        // container in tearDown if needed, but the framework default suffices
        // because we explicitly seed/clear keys per test.
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('booking-confirmation-email-recipient:user-42');
        RateLimiter::clear('booking-confirmation-email-recipient:user-77');
        RateLimiter::clear('unlimited-limiter:any');

        parent::tearDown();
    }

    /**
     * Build a Mockery-backed QueueJob that records release() calls. Using
     * Mockery avoids re-implementing the full Illuminate\Contracts\Queue\Job
     * interface (it grows across Laravel minor versions) and keeps the test
     * focused on the single method ThrottlesPerRecipient actually exercises.
     *
     * @return MockInterface&QueueJob
     */
    private function fakeQueueJob(): MockInterface
    {
        /** @var MockInterface&QueueJob $mock */
        $mock = Mockery::mock(QueueJob::class);
        $mock->shouldReceive('release')->andReturnUsing(function ($delay = 0) use ($mock): void {
            $mock->released = true;
            $mock->releaseDelay = (int) $delay;
        })->byDefault();
        // attempts() is invoked from inside the audit log line when the middleware
        // releases the job; stub it so Mockery does not fail the call.
        $mock->shouldReceive('attempts')->andReturn(1)->byDefault();
        $mock->released = false;
        $mock->releaseDelay = null;

        return $mock;
    }

    /**
     * A user job class that wires InteractsWithQueue to a (mock) QueueJob —
     * this is what Laravel's CallQueuedHandler passes to middleware.
     */
    private function fakeUserJob(int $recipientUserId, QueueJob $queueJob): object
    {
        $job = new class($recipientUserId)
        {
            use InteractsWithQueue;

            public function __construct(public int $recipientUserId) {}

            public function recipientUserId(): int
            {
                return $this->recipientUserId;
            }
        };

        $job->setJob($queueJob);

        return $job;
    }

    public function test_forwards_to_next_when_limit_available_and_records_hit(): void
    {
        RateLimiter::for('booking-confirmation-email-recipient', function (object $job) {
            return Limit::perMinute(5)->by('user-42');
        });

        $queueJob = $this->fakeQueueJob();
        $userJob = $this->fakeUserJob(42, $queueJob);
        $middleware = new ThrottlesPerRecipient('booking-confirmation-email-recipient');

        $nextCalled = false;
        $middleware->handle($userJob, function ($passed) use (&$nextCalled, $userJob): void {
            $this->assertSame($userJob, $passed);
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled, 'middleware must forward to $next when limit not exceeded');
        $this->assertFalse($queueJob->released, 'job must not be released when limit not exceeded');
        $this->assertSame(1, RateLimiter::attempts('booking-confirmation-email-recipient:user-42'));
    }

    public function test_releases_job_when_limit_exceeded_and_does_not_call_next(): void
    {
        RateLimiter::for('booking-confirmation-email-recipient', function (object $job) {
            return Limit::perMinute(5)->by('user-42');
        });

        // Pre-exhaust the bucket (5 hits == limit).
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('booking-confirmation-email-recipient:user-42', 60);
        }

        Log::spy();

        $queueJob = $this->fakeQueueJob();
        $userJob = $this->fakeUserJob(42, $queueJob);
        $middleware = new ThrottlesPerRecipient('booking-confirmation-email-recipient');

        $nextCalled = false;
        $middleware->handle($userJob, function () use (&$nextCalled): void {
            $nextCalled = true;
        });

        $this->assertFalse($nextCalled, 'middleware must NOT call $next when limit exceeded — that would deliver the email');
        $this->assertTrue($queueJob->released, 'job must be released back to the queue');
        $this->assertGreaterThan(0, $queueJob->releaseDelay, 'release delay must reflect availableIn() seconds');

        // Audit log line — required by BL-4 observability checklist.
        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'queue.recipient_throttle.released'
                    && $context['limiter'] === 'booking-confirmation-email-recipient'
                    && $context['recipient_key'] === 'booking-confirmation-email-recipient:user-42';
            });
    }

    public function test_respects_unlimited_limiter(): void
    {
        RateLimiter::for('unlimited-limiter', fn (object $job) => Limit::none());

        $queueJob = $this->fakeQueueJob();
        $userJob = $this->fakeUserJob(77, $queueJob);
        $middleware = new ThrottlesPerRecipient('unlimited-limiter');

        $nextCalled = false;
        $middleware->handle($userJob, function () use (&$nextCalled): void {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);
        $this->assertFalse($queueJob->released);
        $this->assertInstanceOf(Unlimited::class, Limit::none());
    }

    public function test_throws_logic_exception_when_named_limiter_missing(): void
    {
        $queueJob = $this->fakeQueueJob();
        $userJob = $this->fakeUserJob(42, $queueJob);
        $middleware = new ThrottlesPerRecipient('definitely-not-registered');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Named rate limiter [definitely-not-registered] is not registered.');

        $middleware->handle($userJob, fn () => null);
    }
}
