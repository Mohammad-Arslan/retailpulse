<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkCatalogActionRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\CatalogBulkService;
use Illuminate\Http\RedirectResponse;

final class CatalogBulkController extends Controller
{
    public function __construct(
        private readonly CatalogBulkService $catalogBulk,
    ) {}

    public function destroy(BulkCatalogActionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $entity = $validated['entity'];
        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));

        $this->authorizeViewAny($entity);

        $result = $this->catalogBulk->deleteMany($entity, $ids, $request->user());

        return $this->redirectToIndex($entity, $result['deleted'], $result['failed']);
    }

    public function deactivate(BulkCatalogActionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $entity = $validated['entity'];
        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));

        $this->authorizeViewAny($entity);

        $updated = $this->catalogBulk->deactivateMany($entity, $ids, $request->user());

        return redirect()
            ->back()
            ->with(
                'success',
                $updated > 0
                    ? trans_choice('bulk.deactivatedSuccess', $updated, ['count' => $updated])
                    : __('bulk.deactivatedNone'),
            );
    }

    /**
     * @param  list<array{id: int, reason: string}>  $failed
     */
    private function redirectToIndex(string $entity, int $deleted, array $failed): RedirectResponse
    {
        if ($deleted === 0 && $failed !== []) {
            return redirect()
                ->back()
                ->with('error', __('bulk.deleteFailed'));
        }

        if ($failed !== []) {
            return redirect()
                ->back()
                ->with(
                    'warning',
                    __('bulk.deletePartial', [
                        'deleted' => $deleted,
                        'failed' => count($failed),
                    ]),
                );
        }

        return redirect()
            ->back()
            ->with(
                'success',
                trans_choice('bulk.deletedSuccess', $deleted, ['count' => $deleted]),
            );
    }

    private function authorizeViewAny(string $entity): void
    {
        $modelClass = match ($entity) {
            'products' => Product::class,
            'categories' => Category::class,
            'brands' => Brand::class,
            'units' => Unit::class,
            default => Product::class,
        };

        $this->authorize('viewAny', $modelClass);
    }
}
