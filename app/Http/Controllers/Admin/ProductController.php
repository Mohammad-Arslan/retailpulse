<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Product\CreateProductData;
use App\DTOs\Product\UpdateProductData;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\UnitRepositoryInterface;
use App\Services\BranchContextService;
use App\Services\ProductService;
use App\Support\ProductPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly CategoryRepositoryInterface $categories,
        private readonly BrandRepositoryInterface $brands,
        private readonly UnitRepositoryInterface $units,
        private readonly BranchRepositoryInterface $branches,
        private readonly ProductService $productService,
        private readonly BranchContextService $branchContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $paginator = $this->products->paginate(
            $request->only('search', 'type', 'category_id', 'brand_id', 'is_active', 'sort', 'direction'),
        );

        $paginator->getCollection()->transform(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'type' => $product->type->value,
            'is_active' => $product->is_active,
            'category' => $product->category?->only('id', 'name'),
            'brand' => $product->brand?->only('id', 'name'),
            'variants_count' => $product->variants_count,
            'default_variant' => $product->variants->first()?->only([
                'sku', 'sell_price', 'cost_price',
            ]),
        ]);

        return Inertia::render('Admin/Products/Index', [
            'products' => $paginator,
            'filters' => $request->only('search', 'type', 'category_id', 'brand_id', 'is_active', 'sort', 'direction'),
            'productTypes' => ProductType::values(),
            'categories' => $this->categories->allActive(),
            'brands' => $this->brands->allActive(),
            'canShowCost' => $request->user()?->can('products.show-cost') ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Admin/Products/Create', $this->formOptions($request));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->productService->create(CreateProductData::fromRequest($request));

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', __('Product created successfully.'));
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);

        $product = $this->products->findByIdWithRelations($product->id) ?? $product;

        return Inertia::render('Admin/Products/Edit', [
            ...$this->formOptions($request),
            'product' => ProductPresenter::forForm($product),
            'canShowCost' => $request->user()?->can('products.show-cost') ?? false,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->productService->update($product, UpdateProductData::fromRequest($request));

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', __('Product updated successfully.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->productService->delete($product);

        return redirect()
            ->route('admin.products.index')
            ->with('success', __('Product deleted successfully.'));
    }

    public function searchVariants(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $term = (string) $request->query('q', '');
        $excludeProductId = $request->integer('exclude_product_id') ?: null;

        return response()->json(
            $this->products->searchVariants($term, $excludeProductId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'productTypes' => ProductType::values(),
            'categories' => $this->categories->allActive(),
            'brands' => $this->brands->allActive(),
            'units' => $this->units->allActive(),
            'branches' => $this->branches->allActive(
                $this->branchContext->accessibleBranchIds($request->user()),
            ),
            'canShowCost' => $request->user()?->can('products.show-cost') ?? false,
        ];
    }
}
