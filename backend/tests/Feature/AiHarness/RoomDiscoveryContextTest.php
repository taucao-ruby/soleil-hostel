<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\Enums\RiskTier;
use App\AiHarness\Enums\TaskType;
use App\AiHarness\Services\ContextAssemblyService;
use App\AiHarness\Services\ToolOrchestrationService;
use App\Models\Location;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Room Discovery context assembly and tool wiring.
 */
class RoomDiscoveryContextTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_context_assembly_includes_rooms_source(): void
    {
        $location = Location::factory()->create(['is_active' => true]);
        Room::factory()->create([
            'location_id' => $location->id,
            'status' => 'available',
            'name' => 'Phòng Đôi VIP',
        ]);

        $service = app(ContextAssemblyService::class);
        $request = $this->buildRequest('Tìm phòng trống');

        $context = $service->assemble($request);

        $this->assertGreaterThan(0, $context->totalTokens);
        $this->assertNotEmpty($context->sources);

        $roomSource = collect($context->sources)->firstWhere('source_id', 'rooms');
        $this->assertNotNull($roomSource, 'rooms source should be present');
        $this->assertStringContainsString('Phòng Đôi VIP', $roomSource['content']);
    }

    public function test_context_assembly_includes_locations_source(): void
    {
        Location::factory()->create([
            'is_active' => true,
            'name' => 'Soleil Đà Nẵng',
            'slug' => 'soleil-da-nang',
        ]);

        $service = app(ContextAssemblyService::class);
        $request = $this->buildRequest('Chi nhánh nào có phòng');

        $context = $service->assemble($request);

        $locationSource = collect($context->sources)->firstWhere('source_id', 'locations');
        $this->assertNotNull($locationSource, 'locations source should be present');
        $this->assertStringContainsString('Soleil Đà Nẵng', $locationSource['content']);
    }

    public function test_context_assembly_respects_token_budget(): void
    {
        $location = Location::factory()->create(['is_active' => true]);
        // Create many rooms to potentially exceed budget
        Room::factory()->count(50)->create([
            'location_id' => $location->id,
            'status' => 'available',
        ]);

        $service = app(ContextAssemblyService::class);
        $request = $this->buildRequest('Tìm phòng');

        $context = $service->assemble($request);

        $budget = (int) config('ai_harness.token_budget.room_discovery', 4000);
        $this->assertLessThanOrEqual($budget, $context->totalTokens);
    }

    public function test_get_location_detail_returns_data(): void
    {
        $location = Location::factory()->create([
            'is_active' => true,
            'slug' => 'test-location',
            'name' => 'Test Location',
            'address' => '123 Test Street',
        ]);

        Room::factory()->create([
            'location_id' => $location->id,
            'status' => 'available',
            'name' => 'Room A',
            'price' => 300000,
        ]);

        $service = app(ToolOrchestrationService::class);
        $result = $service->execute(
            ['tool' => 'get_location_detail', 'input' => ['slug' => 'test-location']],
            $this->buildRequest('Chi nhánh test')
        );

        $this->assertTrue($result['executed']);
        $this->assertTrue($result['result']['found']);
        $this->assertSame('Test Location', $result['result']['name']);
        $this->assertSame('123 Test Street', $result['result']['address']);
        $this->assertCount(1, $result['result']['rooms']);
        $this->assertSame('Room A', $result['result']['rooms'][0]['name']);
    }

    public function test_get_location_detail_returns_not_found(): void
    {
        $service = app(ToolOrchestrationService::class);
        $result = $service->execute(
            ['tool' => 'get_location_detail', 'input' => ['slug' => 'nonexistent']],
            $this->buildRequest('Chi nhánh cũ')
        );

        $this->assertTrue($result['executed']);
        $this->assertFalse($result['result']['found']);
    }

    public function test_get_location_detail_requires_slug(): void
    {
        $service = app(ToolOrchestrationService::class);
        $result = $service->execute(
            ['tool' => 'get_location_detail', 'input' => []],
            $this->buildRequest('Chi nhánh')
        );

        $this->assertTrue($result['executed']);
        $this->assertArrayHasKey('error', $result['result']);
    }

    public function test_get_location_detail_excludes_inactive(): void
    {
        Location::factory()->create([
            'is_active' => false,
            'slug' => 'closed-branch',
        ]);

        $service = app(ToolOrchestrationService::class);
        $result = $service->execute(
            ['tool' => 'get_location_detail', 'input' => ['slug' => 'closed-branch']],
            $this->buildRequest('Chi nhánh')
        );

        $this->assertTrue($result['executed']);
        $this->assertFalse($result['result']['found']);
    }

    public function test_search_rooms_tool_returns_rooms(): void
    {
        $location = Location::factory()->create(['is_active' => true]);
        Room::factory()->create([
            'location_id' => $location->id,
            'status' => 'available',
        ]);

        $service = app(ToolOrchestrationService::class);
        $result = $service->execute(
            ['tool' => 'search_rooms', 'input' => []],
            $this->buildRequest('Tìm phòng')
        );

        $this->assertTrue($result['executed']);
        $this->assertArrayHasKey('rooms', $result['result']);
    }

    public function test_check_availability_requires_params(): void
    {
        $service = app(ToolOrchestrationService::class);
        $result = $service->execute(
            ['tool' => 'check_availability', 'input' => []],
            $this->buildRequest('Phòng có trống?')
        );

        $this->assertTrue($result['executed']);
        $this->assertFalse($result['result']['available']);
        $this->assertArrayHasKey('error', $result['result']);
    }

    private function buildRequest(string $input): HarnessRequest
    {
        return new HarnessRequest(
            requestId: 'test-room-'.bin2hex(random_bytes(4)),
            correlationId: 'test-corr',
            taskType: TaskType::ROOM_DISCOVERY,
            riskTier: RiskTier::LOW,
            promptVersion: 'room_discovery-v1.0.0',
            userId: $this->user->id,
            userRole: 'user',
            userInput: $input,
            locale: 'vi',
            featureRoute: 'ai.room_discovery',
        );
    }
}
