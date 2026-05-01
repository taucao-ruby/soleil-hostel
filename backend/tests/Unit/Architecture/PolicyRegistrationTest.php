<?php

namespace Tests\Unit\Architecture;

use Illuminate\Support\Facades\Gate;
use ReflectionClass;
use Tests\TestCase;

/**
 * Architecture invariant: each policy-bearing model is registered exactly once.
 *
 * Background (Batch 4, 3A): both AppServiceProvider and AuthServiceProvider used
 * to declare overlapping $policies arrays. Only AuthServiceProvider was active
 * (its parent boot auto-binds), so the AppServiceProvider array was dead code —
 * but the moment someone added parent::boot() there, every overlapping model
 * would silently double-register. This test pins the rule.
 */
class PolicyRegistrationTest extends TestCase
{
    public function test_each_model_is_registered_to_exactly_one_policy(): void
    {
        // Resolve the registered policies via the active Gate facade — this is the
        // runtime view of what Laravel actually uses, not what static arrays declare.
        $reflection = new ReflectionClass(Gate::getFacadeRoot());
        $property = $reflection->getProperty('policies');
        $property->setAccessible(true);

        $registered = $property->getValue(Gate::getFacadeRoot());
        $this->assertIsArray($registered);

        // Each model key MUST appear exactly once. Laravel's Gate::policy() is
        // last-write-wins, so a duplicate registration is silent — only this
        // count check catches it.
        $modelClasses = array_keys($registered);
        $this->assertSame(
            count($modelClasses),
            count(array_unique($modelClasses)),
            'Each model must be registered to exactly one policy (no duplicates).'
        );

        // Pin the canonical bindings — this is the contract.
        $this->assertArrayHasKey(\App\Models\Booking::class, $registered);
        $this->assertArrayHasKey(\App\Models\ContactMessage::class, $registered);
        $this->assertArrayHasKey(\App\Models\Room::class, $registered);
        $this->assertArrayHasKey(\App\Models\Review::class, $registered);

        $this->assertSame(\App\Policies\BookingPolicy::class, $registered[\App\Models\Booking::class]);
        $this->assertSame(\App\Policies\ContactMessagePolicy::class, $registered[\App\Models\ContactMessage::class]);
        $this->assertSame(\App\Policies\RoomPolicy::class, $registered[\App\Models\Room::class]);
        $this->assertSame(\App\Policies\ReviewPolicy::class, $registered[\App\Models\Review::class]);
    }

    public function test_app_service_provider_has_no_policies_array(): void
    {
        // AppServiceProvider must not redeclare $policies — that's a single-source-of-truth
        // invariant. AuthServiceProvider owns it.
        $reflection = new ReflectionClass(\App\Providers\AppServiceProvider::class);
        $this->assertFalse(
            $reflection->hasProperty('policies'),
            'AppServiceProvider must not declare $policies — AuthServiceProvider is the single source.'
        );
    }
}
