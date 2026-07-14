<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\StorePostingRuleSetData;
use App\DTOs\Accounting\UpdatePostingRuleSetData;
use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\PostingRuleEntrySide;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StorePostingRuleSetRequest;
use App\Http\Requests\Admin\Accounting\UpdatePostingRuleSetRequest;
use App\Models\Branch;
use App\Models\OrganizationEntity;
use App\Models\PostingRuleSet;
use App\Services\Accounting\PostingRuleService;
use App\Support\AccountMappingKeys;
use App\Support\ListPagination;
use App\Support\PostingRuleSetPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PostingRuleController extends Controller
{
    public function __construct(
        private readonly PostingRuleService $postingRuleService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PostingRuleSet::class);

        $filters = ListPagination::filters($request, ['search', 'event_type', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        return Inertia::render('Admin/Accounting/PostingRules/Index', [
            'ruleSets' => $this->postingRuleService->paginateIndex($filters, $perPage),
            'filters' => $filters,
        ]);
    }

    public function create(PostingRuleSet $postingRuleSet): Response
    {
        $this->authorize('create', PostingRuleSet::class);

        $source = $this->postingRuleService->findForEdit($postingRuleSet->id);

        abort_if($source === null, 404);

        return Inertia::render('Admin/Accounting/PostingRules/Create', [
            'source' => PostingRuleSetPresenter::forDuplicate($source),
            'accounts' => $this->postingRuleService->postableAccountOptions(),
            'mappingKeys' => AccountMappingKeys::all(),
            'resolutionTypes' => AccountResolutionType::values(),
            'amountSources' => AmountSource::values(),
            'entrySides' => PostingRuleEntrySide::values(),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'legalEntities' => OrganizationEntity::query()
                ->orderBy('legal_name')
                ->get(['id', 'legal_name']),
        ]);
    }

    public function store(StorePostingRuleSetRequest $request): RedirectResponse
    {
        $this->authorize('create', PostingRuleSet::class);

        $data = StorePostingRuleSetData::fromRequest($request);

        $result = $this->postingRuleService->duplicate(
            $data->duplicateFromId,
            $data,
            (int) $request->user()->id,
        );

        $redirect = redirect()
            ->route('admin.accounting.posting-rules.edit', $result->ruleSet)
            ->with('success', __('Posting rule set created successfully.'));

        if ($result->warnings !== []) {
            $redirect->with('warning', $result->warnings[0]);
        }

        return $redirect;
    }

    public function edit(PostingRuleSet $postingRuleSet): Response
    {
        $this->authorize('view', $postingRuleSet);

        $ruleSet = $this->postingRuleService->findForEdit($postingRuleSet->id);

        abort_if($ruleSet === null, 404);

        return Inertia::render('Admin/Accounting/PostingRules/Edit', [
            'ruleSet' => PostingRuleSetPresenter::forEdit($ruleSet),
            'accounts' => $this->postingRuleService->postableAccountOptions(),
            'mappingKeys' => AccountMappingKeys::all(),
            'resolutionTypes' => AccountResolutionType::values(),
            'amountSources' => AmountSource::values(),
            'entrySides' => PostingRuleEntrySide::values(),
        ]);
    }

    public function update(UpdatePostingRuleSetRequest $request, PostingRuleSet $postingRuleSet): RedirectResponse
    {
        $this->authorize('update', $postingRuleSet);

        $this->postingRuleService->update(
            $postingRuleSet,
            UpdatePostingRuleSetData::fromRequest($request),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.accounting.posting-rules.edit', $postingRuleSet)
            ->with('success', __('Posting rule updated successfully.'));
    }
}
