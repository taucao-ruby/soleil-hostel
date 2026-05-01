<?php

namespace Tests\Unit\Logging;

use App\Logging\SensitiveDataProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\UserDataBag;

class SensitiveDataProcessorTest extends TestCase
{
    public function test_redacts_known_pii_keys_from_context(): void
    {
        $record = $this->record(context: [
            'email' => 'guest@example.com',
            'nested' => [
                'guest_name' => 'Ada Lovelace',
            ],
        ]);

        $processed = (new SensitiveDataProcessor)($record);

        $this->assertSame('[REDACTED]', $processed->context['email']);
        $this->assertSame('[REDACTED]', $processed->context['nested']['guest_name']);
        $this->assertStringNotContainsString('guest@example.com', json_encode($processed->context, JSON_THROW_ON_ERROR));
    }

    public function test_replaces_email_patterns_in_message(): void
    {
        $record = $this->record('Booking failed for guest@example.com');

        $processed = (new SensitiveDataProcessor)($record);

        $this->assertSame('Booking failed for [EMAIL]', $processed->message);
    }

    public function test_replaces_payment_and_token_patterns_in_message(): void
    {
        $record = $this->record(
            'Payment pi_123abc failed for 4242 4242 4242 4242 with Bearer secret-token'
        );

        $processed = (new SensitiveDataProcessor)($record);

        $this->assertSame(
            'Payment [REDACTED] failed for [REDACTED] with Bearer [REDACTED]',
            $processed->message
        );
    }

    public function test_redacts_email_patterns_from_extra_for_stdout_and_stderr_logs(): void
    {
        $record = $this->record(extra: [
            'request' => [
                'uri' => '/api/v1/bookings?email=guest@example.com',
            ],
        ]);

        $processed = (new SensitiveDataProcessor)($record);

        $this->assertSame('/api/v1/bookings?email=[EMAIL]', $processed->extra['request']['uri']);
    }

    public function test_sentry_config_disables_default_pii_and_scrubs_events(): void
    {
        $config = require dirname(__DIR__, 3).'/config/sentry.php';
        $event = Event::createEvent();
        $event->setRequest([
            'url' => '/api/v1/bookings',
            'data' => ['guest_email' => 'guest@example.com'],
        ]);
        $event->setUser(new UserDataBag(123, 'guest@example.com'));

        $processed = call_user_func($config['before_send'], $event, null);

        $this->assertFalse($config['send_default_pii']);
        $this->assertIsCallable($config['before_send']);
        $this->assertSame([], $processed->getRequest()['data']);
        $this->assertNull($processed->getUser()?->getEmail());
    }

    public function test_performance_and_stderr_channels_use_sensitive_data_processor(): void
    {
        $loggingConfig = file_get_contents(dirname(__DIR__, 3).'/config/logging.php');

        $this->assertIsString($loggingConfig);
        $this->assertMatchesRegularExpression(
            "/'performance'\\s*=>\\s*\\[.*SensitiveDataProcessor::class/s",
            $loggingConfig
        );
        $this->assertMatchesRegularExpression(
            "/'stderr'\\s*=>\\s*\\[.*SensitiveDataProcessor::class/s",
            $loggingConfig
        );
    }

    private function record(string $message = 'Request completed', array $context = [], array $extra = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'performance',
            level: Level::Info,
            message: $message,
            context: $context,
            extra: $extra,
        );
    }
}
