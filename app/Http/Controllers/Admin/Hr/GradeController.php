<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\DTOs\Hr\CreateGradeData;
use App\DTOs\Hr\UpdateGradeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hr\StoreGradeRequest;
use App\Http\Requests\Admin\Hr\UpdateGradeRequest;
use App\Models\Grade;
use App\Services\Hr\GradeService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GradeController extends Controller
{
    public function __construct(
        private readonly GradeService $grades,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Grade::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'legal_entity_id', 'sort', 'direction']);

        return Inertia::render(
            'Admin/Hr/Grades/Index',
            $this->grades->indexPayload($filters, ListPagination::resolve($filters['per_page'])),
        );
    }

    public function store(StoreGradeRequest $request): RedirectResponse
    {
        $this->authorize('create', Grade::class);

        $this->grades->create(CreateGradeData::fromRequest($request));

        return back()->with('success', __('Grade Created Successfully.'));
    }

    public function update(UpdateGradeRequest $request, Grade $grade): RedirectResponse
    {
        $this->authorize('update', $grade);

        $this->grades->update($grade, UpdateGradeData::fromRequest($request));

        return back()->with('success', __('Grade Updated Successfully.'));
    }
}
