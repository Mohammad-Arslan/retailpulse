<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'tenant_id',
    'entity_type',
    'name',
    'is_default',
    'created_by',
])]
class ImportValidationProfile extends Model
{
    protected $table = 'import_validation_profiles';

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function columnRules(): HasMany
    {
        return $this->hasMany(ImportColumnRule::class, 'profile_id')->orderBy('sort_order');
    }

    public static function defaultFor(int $tenantId, string $entityType): ?self
    {
        return self::query()
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('is_default', true)
            ->with('columnRules')
            ->first();
    }

    /**
     * @return Builder<self>
     */
    public static function forTenantAndEntity(int $tenantId, string $entityType): Builder
    {
        return self::query()
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $entityType);
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     */
    public static function createFromRules(
        int $tenantId,
        string $entityType,
        string $name,
        array $rules,
        bool $setDefault,
        int $createdBy,
    ): self {
        return DB::transaction(function () use ($tenantId, $entityType, $name, $rules, $setDefault, $createdBy) {
            if ($setDefault) {
                self::query()
                    ->where('tenant_id', $tenantId)
                    ->where('entity_type', $entityType)
                    ->update(['is_default' => false]);
            }

            $profile = self::query()->create([
                'tenant_id' => $tenantId,
                'entity_type' => $entityType,
                'name' => $name,
                'is_default' => $setDefault,
                'created_by' => $createdBy,
            ]);

            foreach ($rules as $index => $rule) {
                ImportColumnRule::query()->create([
                    'profile_id' => $profile->id,
                    'column_key' => $rule['column_key'] ?? '',
                    'mapped_to' => $rule['mapped_to'] ?? null,
                    'display_label' => $rule['display_label'] ?? null,
                    'rules' => $rule['rules'] ?? [],
                    'is_required' => (bool) ($rule['is_required'] ?? false),
                    'default_value' => $rule['default_value'] ?? null,
                    'transform' => $rule['transform'] ?? null,
                    'sort_order' => (int) ($rule['sort_order'] ?? $index),
                ]);
            }

            return $profile->load('columnRules');
        });
    }
}
