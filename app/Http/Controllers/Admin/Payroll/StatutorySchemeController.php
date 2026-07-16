<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payroll\StoreStatutorySchemeRequest;
use App\Http\Requests\Admin\Payroll\UpdateStatutorySchemeRequest;
use App\Models\OrganizationEntity;
use App\Models\StatutoryScheme;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StatutorySchemeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StatutoryScheme::class);

        $filters = ListPagination::filters($request, ['legal_entity_id', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = StatutoryScheme::query()
            ->with(['legalEntity:id,legal_name'])
            ->when($filters['legal_entity_id'] ?? null, fn ($q, $id) => $q->where('legal_entity_id', $id))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->orderBy('code');

        $schemes = $query->paginate($perPage)->withQueryString();

        $legalEntities = OrganizationEntity::query()
            ->where('status', 'active')
            ->orderBy('legal_name')
            ->get(['id', 'legal_name']);

        return Inertia::render('Admin/Payroll/StatutorySchemes/Index', [
            'schemes' => $schemes->through(fn (StatutoryScheme $scheme) => [
                'id' => $scheme->id,
                'code' => $scheme->code,
                'name' => $scheme->name,
                'legal_entity_id' => $scheme->legal_entity_id,
                'legal_entity' => $scheme->legalEntity?->legal_name,
                'calculation_type' => $scheme->calculation_type,
                'employee_rate' => (string) $scheme->employee_rate,
                'employer_rate' => (string) $scheme->employer_rate,
                'wage_ceiling' => $scheme->wage_ceiling !== null ? (string) $scheme->wage_ceiling : null,
                'account_mapping_key_employee' => $scheme->account_mapping_key_employee,
                'account_mapping_key_employer' => $scheme->account_mapping_key_employer,
                'effective_from' => $scheme->effective_from?->toDateString(),
                'effective_to' => $scheme->effective_to?->toDateString(),
                'status' => $scheme->status,
            ]),
            'legalEntities' => $legalEntities,
            'filters' => $filters,
        ]);
    }

    public function store(StoreStatutorySchemeRequest $request): RedirectResponse
    {
        $this->authorize('create', StatutoryScheme::class);

        StatutoryScheme::query()->create($request->validated());

        return back()->with('success', __('Statutory Scheme Created Successfully.'));
    }

    public function update(UpdateStatutorySchemeRequest $request, StatutoryScheme $statutoryScheme): RedirectResponse
    {
        $this->authorize('update', $statutoryScheme);

        $statutoryScheme->update($request->validated());

        return back()->with('success', __('Statutory Scheme Updated Successfully.'));
    }
}
