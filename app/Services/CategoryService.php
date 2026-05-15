<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Category\CreateCategoryData;
use App\DTOs\Category\UpdateCategoryData;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    public function create(CreateCategoryData $data): Category
    {
        return DB::transaction(function () use ($data) {
            $category = new Category(['name' => $data->name]);

            return $this->categories->create([
                'name' => $data->name,
                'slug' => UniqueSlug::forModel($category, $data->name),
                'parent_id' => $data->parentId,
                'description' => $data->description,
                'sort_order' => $data->sortOrder,
                'is_active' => $data->isActive,
            ]);
        });
    }

    public function update(Category $category, UpdateCategoryData $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            if ($data->parentId === $category->id) {
                throw ValidationException::withMessages([
                    'parent_id' => __('A category cannot be its own parent.'),
                ]);
            }

            $slug = $category->name !== $data->name
                ? UniqueSlug::forModel($category, $data->name)
                : $category->slug;

            return $this->categories->update($category, [
                'name' => $data->name,
                'slug' => $slug,
                'parent_id' => $data->parentId,
                'description' => $data->description,
                'sort_order' => $data->sortOrder,
                'is_active' => $data->isActive,
            ]);
        });
    }

    public function delete(Category $category): void
    {
        DB::transaction(function () use ($category) {
            if ($category->products()->exists()) {
                throw ValidationException::withMessages([
                    'name' => __('Cannot delete a category that has products.'),
                ]);
            }

            $this->categories->delete($category);
        });
    }
}
