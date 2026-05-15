<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Category\CreateCategoryData;
use App\DTOs\Category\UpdateCategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
        private readonly CategoryService $categoryService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Category::class);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $this->categories->paginate(
                $request->only('search', 'is_active', 'sort', 'direction'),
            ),
            'filters' => $request->only('search', 'is_active', 'sort', 'direction'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Category::class);

        return Inertia::render('Admin/Categories/Create', [
            'parentCategories' => $this->categories->allActive(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $category = $this->categoryService->create(CreateCategoryData::fromRequest($request));

        return redirect()
            ->route('admin.categories.edit', $category)
            ->with('success', __('Category created successfully.'));
    }

    public function edit(Category $category): Response
    {
        $this->authorize('update', $category);

        return Inertia::render('Admin/Categories/Edit', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
                'is_active' => $category->is_active,
            ],
            'parentCategories' => $this->categories->allActive()
                ->where('id', '!=', $category->id)
                ->values(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $this->categoryService->update($category, UpdateCategoryData::fromRequest($request));

        return redirect()
            ->route('admin.categories.edit', $category)
            ->with('success', __('Category updated successfully.'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $this->categoryService->delete($category);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', __('Category deleted successfully.'));
    }
}
