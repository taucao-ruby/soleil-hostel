<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor to mask sensitive data in log records.
 *
 * This processor automatically detects and masks sensitive information
 * like passwords, tokens, credit card numbers, etc.
 */
class SensitiveDataProcessor implements ProcessorInterface
{
    /**
     * Sensitive field patterns to mask.
     *
     * @var array<string>
     */
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'api_key',
        'api_token',
        'access_token',
        'refresh_token',
        'bearer_token',
        'secret',
        'secret_key',
        'private_key',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
        'social_security',
        'authorization',
    ];

    /**
     * The mask string to use for sensitive data.
     */
    protected const MASK = '********';

    /**
     * Process a log record.
     *
     * @param  \Monolog\LogRecord  $record
     * @return \Monolog\LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->maskSensitiveData($record->context);

        return $record->with(context: $context);
    }

    /**
     * Recursively mask sensitive data in an array.
     *
     * @param  array  $data
     * @return array
     */
    protected function maskSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            } elseif ($this->isSensitiveField($key)) {
                $data[$key] = self::MASK;
            } elseif (is_string($value)) {
                $data[$key] = $this->maskSensitivePatterns($value);
            }
        }

        return $data;
    }

    /**
     * Check if a field name is sensitive.
     *
     * @param  string|int  $fieldName
     * @return bool
     */
    protected function isSensitiveField(string|int $fieldName): bool
    {
        if (is_int($fieldName)) {
            return false;
        }

        $fieldName = strtolower($fieldName);

        foreach ($this->sensitiveFields as $sensitiveField) {
            if (str_contains($fieldName, $sensitiveField)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask sensitive patterns in string values.
     *
     * @param  string  $value
     * @return string
     */
    protected function maskSensitivePatterns(string $value): string
    {
        // Mask email addresses (partial)
        $value = preg_replace(
            '/([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/',
            '***@$2',
            $value
        );

        // Mask credit card numbers (keep last 4 digits)
        $value = preg_replace(
            '/\b(\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?)(\d{4})\b/',
            '****-****-****-$2',
            $value
        );

        // Mask Bearer tokens
        $value = preg_replace(
            '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i',
            'Bearer ********',
            $value
        );

        return $value;
    }
}
