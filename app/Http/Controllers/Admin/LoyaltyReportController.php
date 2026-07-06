<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\LoyaltyProgram;
use App\Services\Loyalty\LoyaltyReportService;
use App\Support\BranchContext;
use App\Support\ListPagination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LoyaltyReportController extends Controller
{
    public function __construct(
        private readonly LoyaltyReportService $reports,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LoyaltyProgram::class);

        $branchId = app(BranchContext::class)->branchId;
        $tab = (string) $request->query('tab', 'earned');
        $filters = ListPagination::filters($request, ['from', 'to', 'search', 'program_id']);
        $programId = $request->integer('program_id') ?: null;

        $branchNames = Branch::query()->pluck('name', 'id');

        return Inertia::render('Admin/Loyalty/Reports', [
            'tab' => $tab,
            'filters' => $filters,
            'programs' => LoyaltyProgram::query()->orderBy('name')->get(['id', 'name']),
            'pointsEarned' => $this->reports->pointsEarned($branchId, $filters)->map(fn ($row) => [
                'branch' => $row->branch_id ? ($branchNames[$row->branch_id] ?? __('Branch #:id', ['id' => $row->branch_id])) : __('Global'),
                'total_points' => (int) $row->total_points,
                'transaction_count' => (int) $row->transaction_count,
            ])->values(),
            'pointsRedeemed' => $this->reports->pointsRedeemed($branchId, $filters)->map(fn ($row) => [
                'branch' => $row->branch_id ? ($branchNames[$row->branch_id] ?? __('Branch #:id', ['id' => $row->branch_id])) : __('Global'),
                'total_points' => (int) $row->total_points,
                'transaction_count' => (int) $row->transaction_count,
            ])->values(),
            'pointsExpired' => $this->reports->pointsExpired($branchId, $filters)->map(fn ($row) => [
                'branch' => $row->branch_id ? ($branchNames[$row->branch_id] ?? __('Branch #:id', ['id' => $row->branch_id])) : __('Global'),
                'total_points' => (int) $row->total_points,
                'transaction_count' => (int) $row->transaction_count,
            ])->values(),
            'customerLoyalty' => $this->reports->customerLoyalty($programId, $filters)->map(fn ($w) => [
                'customer' => $w->customer?->name,
                'phone' => $w->customer?->phone,
                'tier' => $w->tier?->name,
                'available_points' => $w->available_points,
                'lifetime_earned_points' => $w->lifetime_earned_points,
            ]),
            'tierDistribution' => $this->reports->tierDistribution($programId)->map(fn ($row) => [
                'tier' => $row->tier?->name ?? __('No Tier'),
                'customer_count' => $row->customer_count,
                'total_points' => $row->total_points,
            ]),
            'branchLoyalty' => $this->reports->branchLoyalty($filters)->map(fn ($row) => [
                'branch' => $row->branch?->name ?? __('Global'),
                'customer_count' => (int) $row->customer_count,
                'available_points' => (int) $row->available_points,
                'lifetime_earned' => (int) $row->lifetime_earned,
                'redeemed_points' => (int) $row->redeemed_points,
            ])->values(),
            'campaignEffectiveness' => $this->reports->campaignEffectiveness($programId, $filters),
            'topCustomers' => $this->reports->topCustomers($programId, $filters)->map(fn ($w) => [
                'customer' => $w->customer?->name,
                'tier' => $w->tier?->name,
                'lifetime_earned_points' => $w->lifetime_earned_points,
                'available_points' => $w->available_points,
            ]),
        ]);
    }
}
