<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class SensitiveDataProcessor implements ProcessorInterface
{
    /**
     * Sensitive field names to redact.
     *
     * @var array<string>
     */
    private const REDACT_KEYS = [
        'email',
        'guest_email',
        'user_email',
        'customer_email',
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'name',
        'guest_name',
        'full_name',
        'first_name',
        'last_name',
        'customer_name',
        'card_number',
        'credit_card',
        'payment_intent_id',
        'stripe_signature',
        'cvv',
        'ssn',
        'social_security',
        'authorization',
        'token',
        'api_key',
        'api_token',
        'access_token',
        'refresh_token',
        'bearer_token',
        'secret',
        'secret_key',
        'private_key',
    ];

    private const REDACT_PATTERN = '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/';

    private const CREDIT_CARD_PATTERN = '/\b(?:\d{4}[\s-]?){3}\d{4}\b/';

    private const BEARER_TOKEN_PATTERN = '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i';

    private const PAYMENT_INTENT_PATTERN = '/\bpi_[A-Za-z0-9_]+\b/';

    private const REDACTED = '[REDACTED]';

    private const EMAIL = '[EMAIL]';

    /**
     * Process a log record.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->maskSensitivePatterns($record->message),
            context: $this->maskSensitiveData($record->context),
            extra: $this->maskSensitiveData($record->extra),
        );
    }

    public static function scrubSentryEvent(\Sentry\Event $event, ?\Sentry\EventHint $hint = null): ?\Sentry\Event
    {
        $request = $event->getRequest();
        $request['data'] = [];
        $event->setRequest($request);

        if ($user = $event->getUser()) {
            $user->setEmail(null);
        }

        return $event;
    }

    /**
     * Recursively redact sensitive data in an array.
     */
    private function maskSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->isSensitiveField($key)) {
                $data[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->maskSensitivePatterns($value);
            }
        }

        return $data;
    }

    /**
     * Check if a field name is sensitive.
     */
    private function isSensitiveField(string|int $fieldName): bool
    {
        if (is_int($fieldName)) {
            return false;
        }

        $fieldName = strtolower($fieldName);

        return in_array($fieldName, self::REDACT_KEYS, true);
    }

    /**
     * Redact sensitive patterns in string values.
     */
    private function maskSensitivePatterns(string $value): string
    {
        $value = preg_replace(self::REDACT_PATTERN, self::EMAIL, $value) ?? $value;
        $value = preg_replace(self::PAYMENT_INTENT_PATTERN, self::REDACTED, $value) ?? $value;
        $value = preg_replace(self::CREDIT_CARD_PATTERN, self::REDACTED, $value) ?? $value;

        return preg_replace(self::BEARER_TOKEN_PATTERN, 'Bearer '.self::REDACTED, $value) ?? $value;
    }
}
