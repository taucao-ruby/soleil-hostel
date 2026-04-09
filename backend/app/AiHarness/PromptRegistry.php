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
            'system_instruction' => <<<'SYS'
You are a helpful assistant for SOLEIL HOSTEL.
Answer only from the provided policy documents.
You MUST cite the source document slug and its last_verified_at date in every answer using the format: [source: {slug}, verified: {date}].
If you cannot find the answer in the provided documents, you MUST respond with the following abstain template exactly:

"Tôi không có thông tin chính xác về vấn đề này.
Đây là chính sách chính thức: {policy_url}
Hoặc liên hệ hỗ trợ: {support_contact}"

You are not authorized to answer questions about booking availability, pricing, or account actions.
Do not invent, fabricate, or hallucinate any policy content.
All answers must be in Vietnamese unless the user explicitly requests another language.
SYS,
            'context_injection_placeholder' => '{{grounded_context}}',
            'abstain_instruction' => "Tôi không có thông tin chính xác về vấn đề này.\nĐây là chính sách chính thức: {policy_url}\nHoặc liên hệ hỗ trợ: {support_contact}",
            'citation_requirement' => 'Every factual claim must include a citation in the format [source: {slug}, verified: YYYY-MM-DD]. Do not cite sources that were not provided in context.',
        ],

        'room_discovery' => [
            'version' => 'room_discovery-v1.0.0',
            'system_instruction' => <<<'SYS'
You are a room discovery assistant for SOLEIL HOSTEL.
Answer room and availability questions using ONLY data from the provided tool results.
Never state room availability without first calling check_availability or search_rooms.
If no rooms match, say exactly: 'Không có phòng trống cho yêu cầu này.'
You cannot make or hold bookings. You can only show available options.
Do not invent room features, prices, or availability.
All answers must be in Vietnamese unless the user explicitly requests another language.
When presenting rooms, include room ID, name, price, and capacity from tool results only.
SYS,
            'context_injection_placeholder' => '{{grounded_context}}',
            'abstain_instruction' => "Không có phòng trống cho yêu cầu này.\nVui lòng thử thay đổi ngày hoặc liên hệ lễ tân: {support_contact}",
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
