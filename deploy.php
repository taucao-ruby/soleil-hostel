#!/usr/bin/env php
<?php

/**
 * Soleil Hostel release helper.
 *
 * Intended entrypoint:
 *   php deploy.php
 *
 * This helper aligns with the current stack and release gates:
 * - Laravel 12 backend
 * - PostgreSQL + Redis
 * - Frontend typecheck, tests, build, and lint
 * - Docker Compose validation
 * - Public health endpoints under /api/health/*
 */

final class DeploymentManager
{
    private string $basePath;

    private string $backendPath;

    private string $frontendPath;

    private string $composeFile;

    private string $timestamp;

    /** @var list<string> */
    private array $successes = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> */
    private array $errors = [];

    public function __construct()
    {
        $this->basePath = realpath(__DIR__) ?: __DIR__;
        $this->backendPath = $this->basePath . DIRECTORY_SEPARATOR . 'backend';
        $this->frontendPath = $this->basePath . DIRECTORY_SEPARATOR . 'frontend';
        $this->composeFile = $this->basePath . DIRECTORY_SEPARATOR . 'docker-compose.yml';
        $this->timestamp = date('Y-m-d H:i:s');
    }

    public function run(): int
    {
        $this->banner('SOLEIL HOSTEL RELEASE HELPER');

        if (! $this->preflight()) {
            return $this->finish(1);
        }

        if (! $this->runGateChecks()) {
            return $this->finish(1);
        }

        if (! $this->runDeployOperations()) {
            return $this->finish(1);
        }

        if (! $this->runVerification()) {
            return $this->finish(1);
        }

        return $this->finish(0);
    }

    private function preflight(): bool
    {
        $this->phase('Preflight');

        $paths = [
            'backend/artisan' => $this->backendPath . DIRECTORY_SEPARATOR . 'artisan',
            'frontend/package.json' => $this->frontendPath . DIRECTORY_SEPARATOR . 'package.json',
            'docker-compose.yml' => $this->composeFile,
        ];

        foreach ($paths as $label => $path) {
            if (! file_exists($path)) {
                $this->recordError("Missing required path: {$label}");
            } else {
                $this->recordSuccess("Found {$label}");
            }
        }

        foreach (['php', 'pnpm', 'docker', 'npx'] as $command) {
            if ($this->findExecutable($command) === null) {
                $this->recordError("Missing required tool on PATH: {$command}");
            } else {
                $this->recordSuccess("Tool available: {$command}");
            }
        }

        $backendEnv = $this->backendPath . DIRECTORY_SEPARATOR . '.env';
        if (! file_exists($backendEnv) && getenv('APP_URL') === false && getenv('DEPLOY_HEALTH_BASE_URL') === false) {
            $this->recordWarning(
                'backend/.env not found and APP_URL is not set; health checks will fall back to http://127.0.0.1:8000'
            );
        }

        return $this->errors === [];
    }

    private function runGateChecks(): bool
    {
        $this->phase('Release Gates');

        $checks = [
            [
                'label' => 'Backend tests',
                'command' => 'php artisan test',
                'cwd' => $this->backendPath,
            ],
            [
                'label' => 'Frontend typecheck',
                'command' => 'npx tsc --noEmit',
                'cwd' => $this->frontendPath,
            ],
            [
                'label' => 'Frontend unit tests',
                'command' => 'pnpm run test:unit',
                'cwd' => $this->frontendPath,
            ],
            [
                'label' => 'Frontend build',
                'command' => 'pnpm run build',
                'cwd' => $this->frontendPath,
            ],
            [
                'label' => 'Frontend lint',
                'command' => 'pnpm run lint',
                'cwd' => $this->frontendPath,
            ],
            [
                'label' => 'Docker Compose config',
                'command' => 'docker compose config',
                'cwd' => $this->basePath,
            ],
        ];

        foreach ($checks as $check) {
            $result = $this->runCommand($check['label'], $check['command'], $check['cwd']);

            if (! $result['ok']) {
                $this->recordError("Gate failed: {$check['label']}");

                return false;
            }

            $this->recordSuccess("Gate passed: {$check['label']}");
        }

        return true;
    }

    private function runDeployOperations(): bool
    {
        $this->phase('Deploy Operations');

        $operations = [
            [
                'label' => 'Database migrations',
                'command' => 'php artisan migrate --force --no-interaction',
            ],
            [
                'label' => 'Optimize clear',
                'command' => 'php artisan optimize:clear',
            ],
            [
                'label' => 'Config cache',
                'command' => 'php artisan config:cache',
            ],
            [
                'label' => 'Route cache',
                'command' => 'php artisan route:cache',
            ],
            [
                'label' => 'View cache',
                'command' => 'php artisan view:cache',
            ],
            [
                'label' => 'Cache warmup',
                'command' => 'php artisan cache:warmup --force --no-progress',
            ],
        ];

        foreach ($operations as $operation) {
            $result = $this->runCommand($operation['label'], $operation['command'], $this->backendPath);

            if (! $result['ok']) {
                $this->recordError("Deploy operation failed: {$operation['label']}");

                return false;
            }

            $this->recordSuccess("Deploy operation completed: {$operation['label']}");
        }

        return true;
    }

    private function runVerification(): bool
    {
        $this->phase('Verification');

        $routeResult = $this->runCommand('Health route list', 'php artisan route:list --path=health', $this->backendPath);
        if (! $routeResult['ok']) {
            $this->recordError('Verification failed: unable to inspect health routes');

            return false;
        }

        foreach (['api/health/live', 'api/health/ready'] as $needle) {
            if (strpos($routeResult['output'], $needle) === false) {
                $this->recordError("Verification failed: missing route {$needle}");

                return false;
            }
        }
        $this->recordSuccess('Verified registered health routes');

        $healthBaseUrl = $this->resolveHealthBaseUrl();
        $this->info("Health base URL: {$healthBaseUrl}");

        foreach (['/api/health/live', '/api/health/ready'] as $path) {
            $url = rtrim($healthBaseUrl, '/') . $path;
            $response = $this->httpGet($url);

            if ($response['status_code'] !== 200) {
                $message = "Verification failed: {$path} returned HTTP {$response['status_code']}";
                if ($response['error'] !== '') {
                    $message .= " ({$response['error']})";
                }
                $this->recordError($message);

                return false;
            }

            $this->recordSuccess("Verified endpoint {$path} (HTTP 200)");
        }

        return true;
    }

    /**
     * @return array{ok: bool, exit_code: int, output: string}
     */
    private function runCommand(string $label, string $command, string $cwd): array
    {
        $this->info("Running {$label}");
        $this->info("Command: {$command}");

        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $cwd);

        if (! is_resource($process)) {
            $this->printBlock('Unable to start command process.');

            return [
                'ok' => false,
                'exit_code' => 1,
                'output' => '',
            ];
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $output = trim($stdout . ($stderr !== '' ? PHP_EOL . $stderr : ''));

        if ($output !== '') {
            $this->printBlock($output);
        }

        if ($exitCode !== 0) {
            $this->error("{$label} failed with exit code {$exitCode}");
        } else {
            $this->success("{$label} completed successfully");
        }

        return [
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => $output,
        ];
    }

    private function resolveHealthBaseUrl(): string
    {
        $override = getenv('DEPLOY_HEALTH_BASE_URL');
        if (is_string($override) && $override !== '') {
            return rtrim($override, '/');
        }

        $envAppUrl = getenv('APP_URL');
        if (is_string($envAppUrl) && $envAppUrl !== '') {
            return rtrim($envAppUrl, '/');
        }

        $backendEnv = $this->parseDotEnv($this->backendPath . DIRECTORY_SEPARATOR . '.env');
        if (isset($backendEnv['APP_URL']) && $backendEnv['APP_URL'] !== '') {
            return rtrim($backendEnv['APP_URL'], '/');
        }

        return 'http://127.0.0.1:8000';
    }

    /**
     * @return array{status_code: int, body: string, error: string}
     */
    private function httpGet(string $url): array
    {
        $error = '';
        $body = '';
        $statusCode = 0;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            $body = '';
            $lastError = error_get_last();
            $error = is_array($lastError) ? (string) ($lastError['message'] ?? '') : '';
        }

        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches) === 1) {
            $statusCode = (int) $matches[1];
        }

        return [
            'status_code' => $statusCode,
            'body' => $body,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseDotEnv(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $parts = explode('=', $trimmed, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $value = trim($value, "\"'");

            $values[$key] = $value;
        }

        return $values;
    }

    private function findExecutable(string $command): ?string
    {
        $pathEnv = getenv('PATH');
        if (! is_string($pathEnv) || $pathEnv === '') {
            return null;
        }

        $paths = explode(PATH_SEPARATOR, $pathEnv);
        $extensions = [''];

        if (DIRECTORY_SEPARATOR === '\\') {
            $pathExt = getenv('PATHEXT');
            $extensions = $pathExt !== false && $pathExt !== ''
                ? explode(';', $pathExt)
                : ['.exe', '.bat', '.cmd', '.com'];

            if (preg_match('/\.[^.]+$/', $command) === 1) {
                $extensions = [''];
            }
        }

        foreach ($paths as $path) {
            foreach ($extensions as $extension) {
                $candidate = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $command . $extension;
                if (DIRECTORY_SEPARATOR === '\\') {
                    if (is_file($candidate)) {
                        return $candidate;
                    }

                    continue;
                }

                if (is_file($candidate) && is_executable($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function finish(int $exitCode): int
    {
        $this->phase('Summary');

        $this->info('Completed at: ' . $this->timestamp);

        $this->printList('Successes', $this->successes);
        $this->printList('Warnings', $this->warnings);
        $this->printList('Errors', $this->errors);

        if ($exitCode === 0) {
            $this->success('Release helper finished successfully.');
        } else {
            $this->error('Release helper finished with errors.');
        }

        return $exitCode;
    }

    private function banner(string $text): void
    {
        $this->line();
        echo str_repeat('=', 72) . PHP_EOL;
        echo $text . PHP_EOL;
        echo str_repeat('=', 72) . PHP_EOL;
        $this->line();
    }

    private function phase(string $name): void
    {
        echo '-- ' . $name . PHP_EOL;
        echo str_repeat('-', max(strlen($name) + 3, 16)) . PHP_EOL;
    }

    /**
     * @param list<string> $items
     */
    private function printList(string $title, array $items): void
    {
        if ($items === []) {
            return;
        }

        echo $title . ':' . PHP_EOL;
        foreach ($items as $item) {
            echo '  - ' . $item . PHP_EOL;
        }
        $this->line();
    }

    private function printBlock(string $text): void
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        foreach ($lines as $line) {
            echo '    ' . $line . PHP_EOL;
        }
    }

    private function recordSuccess(string $message): void
    {
        $this->success($message);
        $this->successes[] = $message;
    }

    private function recordWarning(string $message): void
    {
        $this->warning($message);
        $this->warnings[] = $message;
    }

    private function recordError(string $message): void
    {
        $this->error($message);
        $this->errors[] = $message;
    }

    private function line(): void
    {
        echo PHP_EOL;
    }

    private function info(string $message): void
    {
        echo '[INFO] ' . $message . PHP_EOL;
    }

    private function success(string $message): void
    {
        echo '[PASS] ' . $message . PHP_EOL;
    }

    private function warning(string $message): void
    {
        echo '[WARN] ' . $message . PHP_EOL;
    }

    private function error(string $message): void
    {
        echo '[FAIL] ' . $message . PHP_EOL;
    }
}

$deployment = new DeploymentManager();
exit($deployment->run());
