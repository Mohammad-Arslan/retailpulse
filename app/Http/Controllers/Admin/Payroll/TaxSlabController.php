<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Payroll;

use App\Http\Controllers\Controller;
use App\Models\OrganizationEntity;
use App\Models\TaxSlab;
use App\Support\ListPagination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TaxSlabController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TaxSlab::class);

        $filters = ListPagination::filters($request, ['legal_entity_id', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = TaxSlab::query()
            ->with(['legalEntity:id,legal_name'])
            ->when($filters['legal_entity_id'] ?? null, fn ($q, $id) => $q->where('legal_entity_id', $id))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->orderBy('legal_entity_id')
            ->orderBy('lower_bound');

        $slabs = $query->paginate($perPage)->withQueryString();

        $entities = OrganizationEntity::query()
            ->where('status', 'active')
            ->orderBy('legal_name')
            ->get(['id', 'legal_name']);

        return Inertia::render('Admin/Payroll/TaxSlabs/Index', [
            'slabs' => $slabs->through(fn (TaxSlab $slab) => [
                'id' => $slab->id,
                'legal_entity' => $slab->legalEntity?->legal_name,
                'legal_entity_id' => $slab->legal_entity_id,
                'effective_from' => $slab->effective_from?->toDateString(),
                'effective_to' => $slab->effective_to?->toDateString(),
                'lower_bound' => (string) $slab->lower_bound,
                'upper_bound' => $slab->upper_bound !== null ? (string) $slab->upper_bound : null,
                'fixed_amount' => (string) $slab->fixed_amount,
                'marginal_rate' => (string) $slab->marginal_rate,
                'status' => $slab->status,
            ]),
            'entities' => $entities->map(fn ($e) => ['id' => $e->id, 'name' => $e->legal_name]),
            'filters' => $filters,
        ]);
    }
}
