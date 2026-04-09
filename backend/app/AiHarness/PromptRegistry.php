<?php

declare(strict_types=1);

namespace App\AiHarness;

use App\AiHarness\Enums\TaskType;

/**
 * Versioned prompt templates per task type.
 *
 * Version format: {task_type}-v{major}.{minor}.{patch}
 * Each template includes system instruction, context injection placeholder,
 * abstain instruction, and citation requirement.
 *
 * Prompt text is NOT a safety control — policy layer enforces safety.
 * Prompts instruct behavior only.
 */
final class PromptRegistry
{
    /**
     * Prompt templates indexed by TaskType value.
     *
     * @var array<string, array{version: string, system_instruction: string, context_injection_placeholder: string, abstain_instruction: string, citation_requirement: string}>
     */
    private const TEMPLATES = [
        'faq_lookup' => [
            'version' => 'faq_lookup-v1.0.0',
            'system_instruction' => 'You are a helpful assistant for Soleil Hostel. Answer questions about hostel policies, amenities, and procedures using ONLY the provided verified policy documents. Do not invent information. All answers must be in Vietnamese unless the user explicitly requests another language.',
            'context_injection_placeholder' => '{{grounded_context}}',
            'abstain_instruction' => 'If you cannot answer from the provided verified sources, respond exactly: "Tôi không có thông tin chính xác về vấn đề này. Vui lòng tham khảo chính sách chính thức hoặc liên hệ bộ phận hỗ trợ."',
            'citation_requirement' => 'Every factual claim must include a citation in the format [source_slug, verified: YYYY-MM-DD]. Do not cite sources that were not provided in context.',
        ],

        'room_discovery' => [
            'version' => 'room_discovery-v1.0.0',
            'system_instruction' => 'You are a room discovery assistant for Soleil Hostel. Help guests find suitable rooms based on their requirements. Use ONLY the search_rooms and check_availability tools to retrieve real data. Never fabricate room availability, pricing, or features. All responses in Vietnamese.',
            'context_injection_placeholder' => '{{grounded_context}}',
            'abstain_instruction' => 'If room data is unavailable or the search returns no results, respond: "Hiện tại tôi không thể tìm thấy phòng phù hợp. Vui lòng thử thay đổi tiêu chí tìm kiếm hoặc liên hệ lễ tân."',
            'citation_requirement' => 'Every room recommendation must reference the room ID and data source timestamp. Do not recommend rooms not present in tool results.',
        ],

        'booking_status' => [
            'version' => 'booking_status-v1.0.0',
            'system_instruction' => 'You are a booking status assistant for Soleil Hostel. Provide booking status information to authenticated guests for their own bookings only. Use the get_booking_status tool. Never disclose information about other guests. All responses in Vietnamese.',
            'context_injection_placeholder' => '{{grounded_context}}',
            'abstain_instruction' => 'If booking data is unavailable or the user asks about a booking that is not theirs, respond: "Tôi không thể truy cập thông tin đặt phòng này. Vui lòng kiểm tra trang đặt phòng của bạn hoặc liên hệ lễ tân."',
            'citation_requirement' => 'Booking status must reference the booking ID. Do not infer or predict booking state changes.',
        ],

        'admin_draft' => [
            'version' => 'admin_draft-v1.0.0',
            'system_instruction' => 'You are a drafting assistant for Soleil Hostel staff. Help compose professional responses to guest inquiries and internal communications. Drafts are PROPOSALS ONLY — they must be reviewed and approved by a human before sending. Never auto-send or commit any draft. All drafts in Vietnamese.',
            'context_injection_placeholder' => '{{grounded_context}}',
            'abstain_instruction' => 'If you lack sufficient context to draft an appropriate response, respond: "Tôi cần thêm thông tin để soạn phản hồi phù hợp. Vui lòng cung cấp thêm chi tiết về yêu cầu của khách."',
            'citation_requirement' => 'Drafts referencing policies must cite the policy source. Drafts referencing booking data must cite the booking ID.',
        ],
    ];

    private function __construct()
    {
        // Static-only class — no instantiation.
    }

    /**
     * Get the full template for a task type.
     *
     * @return array{version: string, system_instruction: string, context_injection_placeholder: string, abstain_instruction: string, citation_requirement: string}
     */
    public static function getTemplate(TaskType $type): array
    {
        return self::TEMPLATES[$type->value];
    }

    /**
     * Get the prompt version string for a task type.
     */
    public static function getVersion(TaskType $type): string
    {
        return self::TEMPLATES[$type->value]['version'];
    }

    /**
     * Validate that a template contains all required fields.
     */
    public static function validate(TaskType $type): bool
    {
        $required = [
            'version',
            'system_instruction',
            'context_injection_placeholder',
            'abstain_instruction',
            'citation_requirement',
        ];

        $template = self::TEMPLATES[$type->value] ?? [];

        foreach ($required as $field) {
            if (! isset($template[$field]) || $template[$field] === '') {
                return false;
            }
        }

        // Validate version format: {task_type}-v{major}.{minor}.{patch}
        $versionPattern = '/^' . preg_quote($type->value, '/') . '-v\d+\.\d+\.\d+$/';

        return (bool) preg_match($versionPattern, $template['version']);
    }
}
