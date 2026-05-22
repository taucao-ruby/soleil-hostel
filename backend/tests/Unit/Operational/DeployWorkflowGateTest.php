<?php

declare(strict_types=1);

namespace Tests\Unit\Operational;

use PHPUnit\Framework\TestCase;

final class DeployWorkflowGateTest extends TestCase
{
    public function test_production_deploy_runs_config_assertion_before_health_check(): void
    {
        $workflow = $this->deployWorkflow();

        $migrationPosition = strpos($workflow, '- name: Run database migrations');
        $assertionPosition = strpos($workflow, '- name: Assert production config (pre-traffic)');
        $healthCheckPosition = strpos($workflow, '- name: Health Check');

        $this->assertIsInt($migrationPosition);
        $this->assertIsInt($assertionPosition);
        $this->assertIsInt($healthCheckPosition);
        $this->assertLessThan($assertionPosition, $migrationPosition);
        $this->assertLessThan($healthCheckPosition, $assertionPosition);
        $this->assertStringContainsString('php artisan app:assert-production-config', $workflow);
        $this->assertStringContainsString(
            'cd ${DEPLOY_PATH}/backend && php artisan app:assert-production-config',
            $workflow
        );
    }

    public function test_production_runtime_prerequisite_gate_runs_before_provider_deploy_steps(): void
    {
        $workflow = $this->deployWorkflow();

        $prerequisitePosition = strpos($workflow, '- name: Production runtime gate prerequisites');
        $forgeDeployPosition = strpos($workflow, '- name: Deploy to Forge');

        $this->assertIsInt($prerequisitePosition);
        $this->assertIsInt($forgeDeployPosition);
        $this->assertLessThan($forgeDeployPosition, $prerequisitePosition);
    }

    public function test_production_config_gate_does_not_reference_secrets_in_its_if_condition(): void
    {
        $step = $this->deployWorkflowStep('- name: Assert production config (pre-traffic)');

        $this->assertStringContainsString("if: steps.deploy-target.outputs.environment == 'production'", $step);
        $this->assertStringNotContainsString('if: ${{ secrets.', $step);
        $this->assertStringNotContainsString('if: secrets.', $step);
    }

    public function test_production_runtime_prerequisite_gate_does_not_reference_secrets_in_its_if_condition(): void
    {
        $step = $this->deployWorkflowStep('- name: Production runtime gate prerequisites');

        $this->assertStringContainsString("if: steps.deploy-target.outputs.environment == 'production'", $step);
        $this->assertStringNotContainsString('if: ${{ secrets.', $step);
        $this->assertStringNotContainsString('if: secrets.', $step);
    }

    private function deployWorkflow(): string
    {
        $path = dirname(__DIR__, 4).'/.github/workflows/deploy.yml';

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function deployWorkflowStep(string $stepName): string
    {
        $workflow = $this->deployWorkflow();
        $start = strpos($workflow, $stepName);

        $this->assertIsInt($start);

        $nextStep = strpos($workflow, "\n      - name:", $start + strlen($stepName));

        if ($nextStep === false) {
            return substr($workflow, $start);
        }

        return substr($workflow, $start, $nextStep - $start);
    }
}
