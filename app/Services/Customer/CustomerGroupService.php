<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Models\CustomerGroup;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerGroupService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CustomerGroup
    {
        return DB::transaction(function () use ($attributes) {
            $model = new CustomerGroup(['name' => (string) $attributes['name']]);

            return CustomerGroup::query()->create([
                'name' => $attributes['name'],
                'slug' => UniqueSlug::forModel($model, (string) $attributes['name']),
                'description' => $attributes['description'] ?? null,
                'price_list_id' => $attributes['price_list_id'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CustomerGroup $group, array $attributes): CustomerGroup
    {
        return DB::transaction(function () use ($group, $attributes) {
            $slug = $group->name !== $attributes['name']
                ? UniqueSlug::forModel($group, (string) $attributes['name'])
                : $group->slug;

            $group->update([
                'name' => $attributes['name'],
                'slug' => $slug,
                'description' => $attributes['description'] ?? null,
                'price_list_id' => $attributes['price_list_id'] ?? null,
                'is_active' => $attributes['is_active'] ?? $group->is_active,
            ]);

            return $group->fresh() ?? $group;
        });
    }

    public function delete(CustomerGroup $group): void
    {
        DB::transaction(function () use ($group) {
            if ($group->customers()->exists()) {
                throw ValidationException::withMessages([
                    'name' => __('Cannot delete a customer group that has assigned customers.'),
                ]);
            }

            $group->delete();
        });
    }
}
