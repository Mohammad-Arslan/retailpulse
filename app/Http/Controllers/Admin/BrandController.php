<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Brand\CreateBrandData;
use App\DTOs\Brand\UpdateBrandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\Brand;
use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Services\BrandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BrandController extends Controller
{
    public function __construct(
        private readonly BrandRepositoryInterface $brands,
        private readonly BrandService $brandService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Brand::class);

        return Inertia::render('Admin/Brands/Index', [
            'brands' => $this->brands->paginate(
                $request->only('search', 'is_active', 'sort', 'direction'),
            ),
            'filters' => $request->only('search', 'is_active', 'sort', 'direction'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Brand::class);

        return Inertia::render('Admin/Brands/Create');
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $this->authorize('create', Brand::class);

        $brand = $this->brandService->create(CreateBrandData::fromRequest($request));

        return redirect()
            ->route('admin.brands.edit', $brand)
            ->with('success', __('Brand created successfully.'));
    }

    public function edit(Brand $brand): Response
    {
        $this->authorize('update', $brand);

        return Inertia::render('Admin/Brands/Edit', [
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'description' => $brand->description,
                'is_active' => $brand->is_active,
            ],
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $this->authorize('update', $brand);

        $this->brandService->update($brand, UpdateBrandData::fromRequest($request));

        return redirect()
            ->route('admin.brands.edit', $brand)
            ->with('success', __('Brand updated successfully.'));
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $this->authorize('delete', $brand);

        $this->brandService->delete($brand);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', __('Brand deleted successfully.'));
    }
}
