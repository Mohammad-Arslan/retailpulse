<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Expense\StoreExpenseCategoryRequest;
use App\Http\Requests\Admin\Expense\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ExpenseCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = ExpenseCategory::query()
            ->with('parent:id,name')
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc');

        $categories = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/ExpenseCategories/Index', [
            'categories' => $categories->through(fn (ExpenseCategory $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'parent_id' => $c->parent_id,
                'parent_name' => $c->parent?->name,
                'account_mapping_key' => $c->account_mapping_key,
                'is_group' => $c->is_group,
                'requires_receipt' => $c->requires_receipt,
                'status' => $c->status,
            ]),
            'filters' => $filters,
            'parentOptions' => ExpenseCategory::query()
                ->where('is_group', true)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', ExpenseCategory::class);

        ExpenseCategory::query()->create($request->validated());

        return back()->with('success', __('Expense Category Created Successfully.'));
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('update', $expenseCategory);

        $expenseCategory->update($request->validated());

        return back()->with('success', __('Expense Category Updated Successfully.'));
    }
}
