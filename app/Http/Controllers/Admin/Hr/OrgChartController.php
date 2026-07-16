<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OrganizationEntity;
use App\Services\Hr\ReportingHierarchyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrgChartController extends Controller
{
    public function __construct(
        private readonly ReportingHierarchyService $hierarchy,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $legalEntityId = $request->integer('legal_entity_id') ?: null;
        $rootEmployeeId = $request->integer('root_employee_id') ?: null;

        return Inertia::render('Admin/Hr/OrgChart', [
            'tree' => $this->hierarchy->orgChart($legalEntityId, $rootEmployeeId),
            'filters' => [
                'legal_entity_id' => $legalEntityId,
                'root_employee_id' => $rootEmployeeId,
            ],
            'legalEntities' => OrganizationEntity::query()
                ->orderBy('legal_name')
                ->get(['id', 'legal_name'])
                ->map(fn ($e) => ['id' => $e->id, 'legal_name' => $e->legal_name]),
            'employees' => Employee::query()
                ->where('status', 'active')
                ->when($legalEntityId, fn ($q) => $q->where('legal_entity_id', $legalEntityId))
                ->orderBy('employee_code')
                ->get(['id', 'employee_code', 'first_name', 'last_name'])
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'label' => "{$e->employee_code} — {$e->first_name} {$e->last_name}",
                ]),
        ]);
    }
}
