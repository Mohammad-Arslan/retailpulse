<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Brand\CreateBrandData;
use App\DTOs\Brand\UpdateBrandData;
use App\Models\Brand;
use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BrandService
{
    public function __construct(
        private readonly BrandRepositoryInterface $brands,
    ) {}

    public function create(CreateBrandData $data): Brand
    {
        return DB::transaction(function () use ($data) {
            $brand = new Brand(['name' => $data->name]);

            return $this->brands->create([
                'name' => $data->name,
                'slug' => UniqueSlug::forModel($brand, $data->name),
                'description' => $data->description,
                'is_active' => $data->isActive,
            ]);
        });
    }

    public function update(Brand $brand, UpdateBrandData $data): Brand
    {
        return DB::transaction(function () use ($brand, $data) {
            $slug = $brand->name !== $data->name
                ? UniqueSlug::forModel($brand, $data->name)
                : $brand->slug;

            return $this->brands->update($brand, [
                'name' => $data->name,
                'slug' => $slug,
                'description' => $data->description,
                'is_active' => $data->isActive,
            ]);
        });
    }

    public function delete(Brand $brand): void
    {
        DB::transaction(function () use ($brand) {
            if ($brand->products()->exists()) {
                throw ValidationException::withMessages([
                    'name' => __('Cannot delete a brand that has products.'),
                ]);
            }

            $this->brands->delete($brand);
        });
    }
}
