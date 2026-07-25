<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Payroll;

use App\DTOs\Payroll\CreateSalaryStructureData;
use App\DTOs\Payroll\UpdateSalaryStructureData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payroll\StoreSalaryStructureRequest;
use App\Http\Requests\Admin\Payroll\UpdateSalaryStructureRequest;
use App\Models\OrganizationEntity;
use App\Models\PayComponent;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Services\Payroll\SalaryStructureService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SalaryStructureController extends Controller
{
    public function __construct(
        private readonly SalaryStructureService $salaryStructures,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SalaryStructure::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'legal_entity_id', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $sort = in_array($filters['sort'] ?? '', ['name', 'code', 'status'], true) ? $filters['sort'] : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $structures = SalaryStructure::query()
            ->with(['legalEntity:id,legal_name', 'components.component:id,code,name'])
            ->when($filters['search'] ?? null, fn ($q, string $search) => $q->where(function ($inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
            }))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['legal_entity_id'] ?? null, fn ($q, $id) => $q->where('legal_entity_id', (int) $id))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Payroll/SalaryStructures/Index', [
            'structures' => $structures->through(fn (SalaryStructure $structure) => [
                'id' => $structure->id,
                'code' => $structure->code,
                'name' => $structure->name,
                'legal_entity_id' => $structure->legal_entity_id,
                'legal_entity' => $structure->legalEntity?->legal_name,
                'status' => $structure->status,
                'components' => $structure->components->map(fn (SalaryStructureComponent $c) => [
                    'id' => $c->id,
                    'pay_component_id' => $c->pay_component_id,
                    'pay_component' => $c->component ? "{$c->component->code} — {$c->component->name}" : null,
                    'amount_or_rate' => $c->amount_or_rate !== null ? (string) $c->amount_or_rate : null,
                    'sequence' => $c->sequence,
                ])->values(),
            ]),
            'filters' => $filters,
            'legalEntities' => OrganizationEntity::query()
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name']),
            'payComponents' => PayComponent::query()
                ->where('status', 'active')
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'nextCode' => $this->salaryStructures->nextCode(),
        ]);
    }

    public function store(StoreSalaryStructureRequest $request): RedirectResponse
    {
        $this->authorize('create', SalaryStructure::class);

        $this->salaryStructures->create(CreateSalaryStructureData::fromRequest($request));

        return back()->with('success', __('Salary Structure Created Successfully.'));
    }

    public function update(UpdateSalaryStructureRequest $request, SalaryStructure $salaryStructure): RedirectResponse
    {
        $this->authorize('update', $salaryStructure);

        $this->salaryStructures->update($salaryStructure, UpdateSalaryStructureData::fromRequest($request));

        return back()->with('success', __('Salary Structure Updated Successfully.'));
    }
}
