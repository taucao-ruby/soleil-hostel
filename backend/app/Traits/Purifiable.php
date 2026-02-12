<?php

namespace App\Traits;

use App\Services\HtmlPurifierService;
use Illuminate\Database\Eloquent\Model;

/**
 * Purifiable trait.
 *
 * Automatically purifies configured HTML fields when models are persisted.
 */
trait Purifiable
{
    /**
     * Fields to auto-purify.
     *
     * @var list<string>
     */
    protected array $purifiable = [];

    /**
     * Per-field purifier config overrides.
     *
     * @var array<string, array<array-key, mixed>>
     */
    protected array $purifiable_config = [];

    /**
     * Register model callbacks.
     */
    public static function bootPurifiable(): void
    {
        static::saving(static function (Model $model): void {
            if (! method_exists($model, 'getPurifiableFields')) {
                return;
            }

            /** @var array<array-key, mixed> $fields */
            $fields = $model->getPurifiableFields();
            foreach ($fields as $field) {
                if (! is_string($field) || ! $model->isDirty($field)) {
                    continue;
                }

                $value = $model->getAttribute($field);
                if (! is_string($value)) {
                    continue;
                }

                $config = method_exists($model, 'getPurifiableConfig')
                    ? $model->getPurifiableConfig($field)
                    : [];

                if (! is_array($config)) {
                    $config = [];
                }

                $model->setAttribute(
                    $field,
                    HtmlPurifierService::purify($value, $config)
                );
            }
        });

        static::retrieved(static function (Model $model): void {
            if (method_exists($model, 'getPurifiableFields')) {
                // Keep hook for optional retrieve-time purification.
            }
        });
    }

    /**
     * @return list<string>
     */
    public function getPurifiableFields(): array
    {
        /** @var list<string> $fields */
        $fields = array_values(array_filter(
            $this->purifiable,
            static fn ($field): bool => is_string($field)
        ));

        return $fields;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getPurifiableConfig(string $field): array
    {
        $config = $this->purifiable_config[$field] ?? [];

        return is_array($config) ? $config : [];
    }

    /**
     * @param  mixed  $key
     * @param  mixed  $value
     */
    public function setAttribute($key, $value): static
    {
        if (! is_string($key)) {
            return parent::setAttribute((string) $key, $value);
        }

        if (in_array($key, $this->getPurifiableFields(), true) && is_string($value)) {
            $value = HtmlPurifierService::purify(
                $value,
                $this->getPurifiableConfig($key)
            );
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * @param  mixed  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (! is_string($key)) {
            return null;
        }

        return parent::getAttribute($key);
    }
}
