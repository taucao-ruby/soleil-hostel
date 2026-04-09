<?php

declare(strict_types=1);

namespace Tests\Unit\AiHarness;

use App\AiHarness\Enums\TaskType;
use App\AiHarness\PromptRegistry;
use PHPUnit\Framework\TestCase;

class PromptRegistryTest extends TestCase
{
    public function test_all_task_types_have_a_template(): void
    {
        foreach (TaskType::cases() as $type) {
            $template = PromptRegistry::getTemplate($type);

            $this->assertNotEmpty($template, "TaskType::{$type->name} must have a template");
            $this->assertArrayHasKey('version', $template);
            $this->assertArrayHasKey('system_instruction', $template);
            $this->assertArrayHasKey('context_injection_placeholder', $template);
            $this->assertArrayHasKey('abstain_instruction', $template);
            $this->assertArrayHasKey('citation_requirement', $template);
        }
    }

    public function test_prompt_version_format_is_valid_semver(): void
    {
        foreach (TaskType::cases() as $type) {
            $version = PromptRegistry::getVersion($type);

            $this->assertMatchesRegularExpression(
                '/^' . preg_quote($type->value, '/') . '-v\d+\.\d+\.\d+$/',
                $version,
                "Version for {$type->name} must match format {task_type}-v{major}.{minor}.{patch}",
            );
        }
    }

    public function test_each_template_contains_abstain_instruction(): void
    {
        foreach (TaskType::cases() as $type) {
            $template = PromptRegistry::getTemplate($type);

            $this->assertNotEmpty(
                $template['abstain_instruction'],
                "TaskType::{$type->name} must have a non-empty abstain_instruction",
            );

            // Abstain instructions must not encourage the model to guess
            $this->assertStringNotContainsString(
                'I think',
                $template['abstain_instruction'],
                "Abstain instruction for {$type->name} must not contain speculative language",
            );
        }
    }

    public function test_each_grounded_template_contains_citation_requirement(): void
    {
        // All current task types are grounded (they use verified data sources)
        foreach (TaskType::cases() as $type) {
            $template = PromptRegistry::getTemplate($type);

            $this->assertNotEmpty(
                $template['citation_requirement'],
                "TaskType::{$type->name} must have a non-empty citation_requirement",
            );
        }
    }

    public function test_validate_returns_true_for_all_current_templates(): void
    {
        foreach (TaskType::cases() as $type) {
            $this->assertTrue(
                PromptRegistry::validate($type),
                "PromptRegistry::validate() must return true for TaskType::{$type->name}",
            );
        }
    }
}
