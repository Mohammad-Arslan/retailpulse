<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payroll\StorePayComponentRequest;
use App\Http\Requests\Admin\Payroll\UpdatePayComponentRequest;
use App\Models\OrganizationEntity;
use App\Models\PayComponent;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PayComponentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PayComponent::class);

        $filters = ListPagination::filters($request, ['search', 'type', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = PayComponent::query()
            ->with(['basisComponent:id,code,name', 'legalEntity:id,legal_name'])
            ->when($filters['search'] ?? null, fn ($q, string $search) => $q->where(function ($inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
            }))
            ->when($filters['type'] ?? null, fn ($q, string $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->orderBy($filters['sort'] ?? 'code', $filters['direction'] ?? 'asc');

        $components = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Payroll/PayComponents/Index', [
            'components' => $components->through(fn (PayComponent $component) => [
                'id' => $component->id,
                'code' => $component->code,
                'name' => $component->name,
                'type' => $component->type,
                'calculation_type' => $component->calculation_type,
                'basis_component_id' => $component->basis_component_id,
                'basis_component' => $component->basisComponent?->code,
                'rate' => $component->rate !== null ? (string) $component->rate : null,
                'taxable' => $component->taxable,
                'account_mapping_key' => $component->account_mapping_key,
                'effective_from' => $component->effective_from?->toDateString(),
                'effective_to' => $component->effective_to?->toDateString(),
                'legal_entity_id' => $component->legal_entity_id,
                'legal_entity' => $component->legalEntity?->legal_name,
                'status' => $component->status,
            ]),
            'filters' => $filters,
            'legalEntities' => OrganizationEntity::query()
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name']),
            'basisOptions' => PayComponent::query()
                ->where('status', 'active')
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function store(StorePayComponentRequest $request): RedirectResponse
    {
        $this->authorize('create', PayComponent::class);

        PayComponent::query()->create($request->validated());

        return back()->with('success', __('Pay Component Created Successfully.'));
    }

    public function update(UpdatePayComponentRequest $request, PayComponent $payComponent): RedirectResponse
    {
        $this->authorize('update', $payComponent);

        $payComponent->update($request->validated());

        return back()->with('success', __('Pay Component Updated Successfully.'));
    }

    public function destroy(Request $request, PayComponent $payComponent): RedirectResponse
    {
        $this->authorize('delete', $payComponent);

        $payComponent->delete();

        return back()->with('success', __('Pay Component Deleted Successfully.'));
    }
}
