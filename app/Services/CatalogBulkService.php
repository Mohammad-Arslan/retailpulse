<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\UnitRepositoryInterface;
use App\Support\TenantImportScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CatalogBulkService
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly CategoryService $categoryService,
        private readonly BrandService $brandService,
        private readonly UnitService $unitService,
        private readonly ProductRepositoryInterface $products,
        private readonly CategoryRepositoryInterface $categories,
        private readonly BrandRepositoryInterface $brands,
        private readonly UnitRepositoryInterface $units,
    ) {}

    /**
     * @param  list<int>  $ids
     * @return array{deleted: int, failed: list<array{id: int, reason: string}>}
     */
    public function deleteMany(string $entity, array $ids, User $user): array
    {
        $deleted = 0;
        $failed = [];

        foreach ($ids as $id) {
            try {
                $model = $this->resolveModel($entity, (int) $id, $user);

                if ($model === null) {
                    $failed[] = [
                        'id' => (int) $id,
                        'reason' => __('The selected record could not be found.'),
                    ];

                    continue;
                }

                Gate::authorize('delete', $model);

                match ($entity) {
                    'products' => $this->productService->delete($model),
                    'categories' => $this->categoryService->delete($model),
                    'brands' => $this->brandService->delete($model),
                    'units' => $this->unitService->delete($model),
                    default => throw new AuthorizationException,
                };

                $deleted++;
            } catch (AuthorizationException $exception) {
                $failed[] = [
                    'id' => (int) $id,
                    'reason' => $exception->getMessage() !== ''
                        ? $exception->getMessage()
                        : __('You are not allowed to delete this record.'),
                ];
            } catch (ValidationException $exception) {
                $failed[] = [
                    'id' => (int) $id,
                    'reason' => collect($exception->errors())->flatten()->first()
                        ?? __('This record could not be deleted.'),
                ];
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }

    /**
     * @param  list<int>  $ids
     */
    public function deactivateMany(string $entity, array $ids, User $user): int
    {
        return DB::transaction(function () use ($entity, $ids, $user) {
            $updated = 0;

            foreach ($ids as $id) {
                $model = $this->resolveModel($entity, (int) $id, $user);

                if ($model === null) {
                    continue;
                }

                Gate::authorize('update', $model);

                if ($model->is_active === false) {
                    continue;
                }

                match ($entity) {
                    'products' => $this->products->update($model, ['is_active' => false]),
                    'categories' => $this->categories->update($model, ['is_active' => false]),
                    'brands' => $this->brands->update($model, ['is_active' => false]),
                    'units' => $this->units->update($model, ['is_active' => false]),
                    default => null,
                };

                $updated++;
            }

            return $updated;
        });
    }

    private function resolveModel(string $entity, int $id, User $user): Product|Category|Brand|Unit|null
    {
        $tenantId = TenantImportScope::normalize($user->tenant_id);

        return match ($entity) {
            'products' => $this->findScoped(Product::query(), $tenantId, $id),
            'categories' => $this->findScoped(Category::query(), $tenantId, $id),
            'brands' => $this->findScoped(Brand::query(), $tenantId, $id),
            'units' => $this->findScoped(Unit::query(), $tenantId, $id),
            default => null,
        };
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Model>  $query
     */
    private function findScoped($query, ?int $tenantId, int $id): Product|Category|Brand|Unit|null
    {
        /** @var Product|Category|Brand|Unit|null $model */
        $model = TenantImportScope::constrain($query, $tenantId)
            ->whereKey($id)
            ->first();

        return $model;
    }
}
