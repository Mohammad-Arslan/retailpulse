<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hr\StoreApprovalDelegationRequest;
use App\Http\Requests\Admin\Hr\UpdateApprovalDelegationRequest;
use App\Models\ApprovalDelegation;
use App\Services\Hr\ApprovalDelegationService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ApprovalDelegationController extends Controller
{
    public function __construct(
        private readonly ApprovalDelegationService $delegations,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ApprovalDelegation::class);

        $filters = ListPagination::filters($request, ['status', 'scope', 'sort', 'direction']);

        return Inertia::render(
            'Admin/Hr/Delegations/Index',
            $this->delegations->indexPayload($filters, ListPagination::resolve($filters['per_page'])),
        );
    }

    public function store(StoreApprovalDelegationRequest $request): RedirectResponse
    {
        $this->authorize('create', ApprovalDelegation::class);

        $this->delegations->create($request->validated());

        return back()->with('success', __('Approval Delegation Created Successfully.'));
    }

    public function update(UpdateApprovalDelegationRequest $request, ApprovalDelegation $delegation): RedirectResponse
    {
        $this->authorize('update', $delegation);

        $this->delegations->update($delegation, $request->validated());

        return back()->with('success', __('Approval Delegation Updated Successfully.'));
    }
}
