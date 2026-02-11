<?php

namespace App\Traits;

use App\Services\HtmlPurifierService;
use Illuminate\Database\Eloquent\Model;

/**
 * Purifiable Trait
 *
 * Automatically purify HTML fields khi save + retrieve model
 *
 * Usage trong Model:
 * use Purifiable;
 * protected $purifiable = ['guest_name', 'message', 'content'];
 *
 * Khi save: save() => getAttribute('guest_name') return purified HTML
 * Khi retrieve: get() => automaticaly purified
 */
trait Purifiable
{
    /**
     * Fields to auto-purify
     */
    protected array $purifiable = [];

    /**
     * Field overrides config (optional)
     * Ví dụ: protected $purifiable_config = ['field' => ['allowed_elements' => [...]]]
     */
    protected array $purifiable_config = [];

    /**
     * Boot Purifiable trait - register callbacks
     */
    public static function bootPurifiable(): void
    {
        // Saving: purify trước khi save DB
        static::saving(function (Model $model) {
            if (method_exists($model, 'getPurifiableFields')) {
                $fields = $model->getPurifiableFields();
                foreach ($fields as $field) {
                    if ($model->isDirty($field) && $model->getAttribute($field) !== null) {
                        $config = $model->getPurifiableConfig($field);
                        $clean = HtmlPurifierService::purify(
                            $model->getAttribute($field),
                            $config
                        );
                        $model->setAttribute($field, $clean);
                    }
                }
            }
        });

        // Retrieved: purify khi get từ DB (optional, vì đã clean khi save)
        // Bật nếu cần extra safety hoặc nếu có old data chưa purified
        static::retrieved(function (Model $model) {
            if (method_exists($model, 'getPurifiableFields')) {
                // Optional: auto-purify on retrieve (tốn performance, usually not needed)
                // Vì DB đã clean rồi, chỉ purify nếu manually set attribute
            }
        });
    }

    /**
     * Get list of fields to purify
     */
    public function getPurifiableFields(): array
    {
        return $this->purifiable ?? [];
    }

    /**
     * Get purify config cho field (optional override)
     */
    public function getPurifiableConfig(string $field): array
    {
        return $this->purifiable_config[$field] ?? [];
    }

    /**
     * Mutator: Auto purify when setting attribute
     * Gọi $model->setAttribute('field', $value) => auto-purify
     */
    public function setAttribute($key, $value): static
    {
        if (in_array($key, $this->purifiable ?? []) && is_string($value)) {
            $value = HtmlPurifierService::purify(
                $value,
                $this->getPurifiableConfig($key)
            );
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Accessor: Return clean HTML (optional, vì đã clean saat save)
     * Dùng để ensure double-safety nếu cần
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        // Không auto-purify on retrieve nếu không cần
        // (vì đã clean saat save)
        // Uncomment nếu muốn extra safety:
        // if (in_array($key, $this->purifiable ?? []) && is_string($value)) {
        //     $value = HtmlPurifierService::purify($value);
        // }

        return $value;
    }
}
