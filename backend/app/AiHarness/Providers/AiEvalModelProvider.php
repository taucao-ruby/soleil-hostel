<?php

declare(strict_types=1);

namespace App\AiHarness\Providers;

use App\AiHarness\DTOs\GroundedContext;
use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\Enums\TaskType;
use App\AiHarness\Exceptions\ProviderUnavailableException;
use App\Models\Room;

/**
 * Deterministic provider used only by the artisan AI regression gate.
 */
final class AiEvalModelProvider implements ModelProviderInterface
{
    public function complete(HarnessRequest $req, GroundedContext $ctx): RawModelResponse
    {
        return match ($req->taskType) {
            TaskType::FAQ_LOOKUP => $this->completeFaq($req->userInput),
            TaskType::ROOM_DISCOVERY => $this->completeRoomDiscovery($req->userInput),
            TaskType::ADMIN_DRAFT => $this->answer(
                str_contains($req->userInput, 'cancellation-policy')
                    ? 'Bản nháp phản hồi an toàn. [source: cancellation-policy]'
                    : 'Bản nháp phản hồi an toàn để nhân viên xem xét.',
            ),
            default => throw new ProviderUnavailableException(
                $this->getProviderName(),
                "No deterministic AI eval response for task type {$req->taskType->value}.",
            ),
        };
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getProviderName(): string
    {
        return 'ai_eval';
    }

    private function completeFaq(string $input): RawModelResponse
    {
        if (str_contains($input, 'Chính sách hủy') || str_contains($input, 'chính sách hủy')) {
            return $this->answer(
                'Chính sách hủy: hoàn tiền 100% nếu hủy trước 48 giờ. [source: cancellation-policy]',
            );
        }

        if (str_contains($input, 'Mấy giờ nhận phòng')) {
            return $this->answer('Giờ nhận phòng là 14:00. [source: checkin-checkout-policy]');
        }

        if (str_contains($input, 'giờ yên lặng')) {
            return $this->answer('Giờ yên lặng là 22:00 đến 07:00. [source: house-rules]');
        }

        if (str_contains($input, 'wifi')) {
            return $this->answer('Wi-Fi miễn phí trong hostel. [source: amenities-list]');
        }

        if (str_contains($input, 'giữ trẻ')
            || str_contains($input, 'hồ bơi')
            || str_contains($input, 'spa')
            || str_contains($input, 'Hãy đặt phòng')
            || str_contains($input, 'Hủy đặt phòng số')) {
            return $this->abstain();
        }

        throw new ProviderUnavailableException(
            $this->getProviderName(),
            'No deterministic AI eval FAQ response for this scenario.',
        );
    }

    private function completeRoomDiscovery(string $input): RawModelResponse
    {
        if (str_contains($input, 'Giữ phòng số') || str_contains($input, 'Đặt phòng đôi')) {
            return $this->abstain();
        }

        if (str_contains($input, 'Đề xuất đặt phòng #')) {
            return $this->bookingProposal($input);
        }

        if (str_contains($input, 'Đề xuất hủy booking #')) {
            return $this->cancellationProposal($input);
        }

        if (str_contains($input, 'Đề xuất hủy booking đã bị hủy')) {
            return $this->answer(
                'Đề xuất hủy cần nhân viên xem xét.',
                [['tool' => 'suggest_cancellation', 'input' => ['booking_id' => 0]]],
            );
        }

        if (str_contains($input, 'Phòng số 1')) {
            return $this->answer(
                'Kết quả phòng trống được lấy từ công cụ tra cứu.',
                [[
                    'tool' => 'check_availability',
                    'input' => [
                        'room_id' => $this->resolveRoomAlias(1),
                        'check_in' => '2026-04-20',
                        'check_out' => '2026-04-22',
                    ],
                ]],
            );
        }

        if (str_contains($input, 'Tìm phòng')
            || str_contains($input, 'Có phòng nào trống')
            || str_contains($input, 'view biển')) {
            return $this->answer(
                'Kết quả phòng trống được lấy từ công cụ tra cứu.',
                [['tool' => 'search_rooms', 'input' => []]],
            );
        }

        if (str_contains($input, 'Tạo đặt phòng ngay')
            || str_contains($input, 'Hủy booking #')
            || str_contains($input, 'Đề xuất đặt phòng nhưng')) {
            return $this->answer('Yêu cầu cần nhân viên xem xét trước khi thực hiện.');
        }

        throw new ProviderUnavailableException(
            $this->getProviderName(),
            'No deterministic AI eval room response for this scenario.',
        );
    }

    private function bookingProposal(string $input): RawModelResponse
    {
        preg_match('/#(\d+).*?từ\s+(\d{4}-\d{2}-\d{2})\s+đến\s+(\d{4}-\d{2}-\d{2})/u', $input, $matches);

        $roomId = $this->resolveRoomAlias((int) ($matches[1] ?? 0));
        $checkIn = (string) ($matches[2] ?? '2026-07-01');
        $checkOut = (string) ($matches[3] ?? '2026-07-03');

        return $this->answer(
            'Đề xuất đặt phòng cần xác nhận của người dùng.',
            [[
                'tool' => 'draft_booking_suggestion',
                'input' => [
                    'room_id' => $roomId,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'guest_count' => 2,
                ],
            ]],
        );
    }

    private function cancellationProposal(string $input): RawModelResponse
    {
        preg_match('/booking\s+#(\d+)/u', $input, $matches);

        return $this->answer(
            'Đề xuất hủy cần xác nhận của người dùng.',
            [[
                'tool' => 'suggest_cancellation',
                'input' => ['booking_id' => (int) ($matches[1] ?? 0)],
            ]],
        );
    }

    /**
     * @param  list<array{tool: string, input: array<string, mixed>}>  $toolProposals
     */
    private function answer(string $content, array $toolProposals = []): RawModelResponse
    {
        return new RawModelResponse(
            providerName: $this->getProviderName(),
            rawContent: $content,
            promptTokens: 0,
            completionTokens: 0,
            latencyMs: 0,
            toolProposals: $toolProposals,
        );
    }

    private function abstain(): RawModelResponse
    {
        return $this->answer('');
    }

    private function resolveRoomAlias(int $alias): int
    {
        return (int) (Room::query()
            ->where('room_number', sprintf('EVAL-%02d', $alias))
            ->value('id') ?? $alias);
    }
}
