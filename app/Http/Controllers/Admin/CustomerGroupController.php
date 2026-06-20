<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerGroupRequest;
use App\Http\Requests\Admin\UpdateCustomerGroupRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Services\Customer\CustomerGroupService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerGroupController extends Controller
{
    public function __construct(
        private readonly CustomerGroupService $groups,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $filters = ListPagination::filters($request, ['search', 'is_active', 'sort', 'direction']);

        $query = CustomerGroup::query()->withCount('customers');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sort = (string) ($filters['sort'] ?? 'name');
        $direction = (string) ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['name', 'created_at'], true)) {
            $sort = 'name';
        }

        $groups = $query
            ->orderBy($sort, $direction)
            ->paginate(ListPagination::resolve($filters['per_page']))
            ->withQueryString()
            ->through(fn (CustomerGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => $group->description,
                'is_active' => $group->is_active,
                'customers_count' => $group->customers_count,
            ]);

        return Inertia::render('Admin/CustomerGroups/Index', [
            'customerGroups' => $groups,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Customer::class);

        return Inertia::render('Admin/CustomerGroups/Create');
    }

    public function store(StoreCustomerGroupRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $group = $this->groups->create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'price_list_id' => $request->validated('price_list_id'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.customer-groups.edit', $group)
            ->with('success', __('Customer group created successfully.'));
    }

    public function edit(CustomerGroup $customerGroup): Response
    {
        $this->authorize('update', Customer::make());

        return Inertia::render('Admin/CustomerGroups/Edit', [
            'group' => [
                'id' => $customerGroup->id,
                'name' => $customerGroup->name,
                'slug' => $customerGroup->slug,
                'description' => $customerGroup->description,
                'price_list_id' => $customerGroup->price_list_id,
                'is_active' => $customerGroup->is_active,
            ],
        ]);
    }

    public function update(UpdateCustomerGroupRequest $request, CustomerGroup $customerGroup): RedirectResponse
    {
        $this->authorize('update', Customer::make());

        $this->groups->update($customerGroup, [
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'price_list_id' => $request->validated('price_list_id'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.customer-groups.edit', $customerGroup)
            ->with('success', __('Customer group updated successfully.'));
    }

    public function destroy(CustomerGroup $customerGroup): RedirectResponse
    {
        $this->authorize('delete', Customer::make());

        $this->groups->delete($customerGroup);

        return redirect()
            ->route('admin.customer-groups.index')
            ->with('success', __('Customer group deleted successfully.'));
    }
}
