<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Services\Customer\ArAgingService;
use App\Support\BranchContext;
use App\Support\ListPagination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArAgingController extends Controller
{
    public function __construct(
        private readonly ArAgingService $aging,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('customers.view-credit'), 403);

        $context = app(BranchContext::class);
        $filters = ListPagination::filters(
            $request,
            ['search', 'branch_id', 'customer_group_id', 'snapshot_date', 'sort', 'direction'],
        );

        if ($context->branchId !== null && empty($filters['branch_id'])) {
            $filters['branch_id'] = $context->branchId;
        }

        $report = $this->aging->paginateReport(
            $filters,
            ListPagination::resolve($filters['per_page']),
        );

        return Inertia::render('Admin/ArAging/Index', [
            'aging' => $report->through(fn ($snapshot): array => [
                'id' => $snapshot->id,
                'customer_id' => $snapshot->customer_id,
                'snapshot_date' => $snapshot->snapshot_date?->toDateString(),
                'customer' => $snapshot->customer ? [
                    'id' => $snapshot->customer->id,
                    'name' => $snapshot->customer->name,
                    'phone' => $snapshot->customer->phone,
                    'customer_group' => $snapshot->customer->customerGroup ? [
                        'name' => $snapshot->customer->customerGroup->name,
                    ] : null,
                ] : null,
                'branch' => $snapshot->branch ? ['name' => $snapshot->branch->name] : null,
                'current' => number_format((float) $snapshot->current, 2, '.', ''),
                'bucket_30' => number_format((float) $snapshot->bucket_30, 2, '.', ''),
                'bucket_60' => number_format((float) $snapshot->bucket_60, 2, '.', ''),
                'bucket_90' => number_format((float) $snapshot->bucket_90, 2, '.', ''),
                'bucket_over_90' => number_format((float) $snapshot->bucket_over_90, 2, '.', ''),
                'total_outstanding' => number_format((float) $snapshot->total_outstanding, 2, '.', ''),
            ]),
            'filters' => $filters,
            'customerGroups' => CustomerGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'snapshotDate' => $filters['snapshot_date'] ?? now()->toDateString(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Customer::class);

        $context = app(BranchContext::class);
        $filters = $request->only(['branch_id', 'customer_group_id', 'snapshot_date']);

        if ($context->branchId !== null && empty($filters['branch_id'])) {
            $filters['branch_id'] = $context->branchId;
        }

        $rows = $this->aging->exportRows($filters);
        $filename = 'ar-aging-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Snapshot Date', 'Customer', 'Phone', 'Group', 'Branch',
                'Current', '31-60', '61-90', '91-120', 'Over 90', 'Total Outstanding',
            ]);

            foreach ($rows as $snapshot) {
                fputcsv($handle, [
                    $snapshot->snapshot_date?->toDateString(),
                    $snapshot->customer?->name,
                    $snapshot->customer?->phone,
                    $snapshot->customer?->customerGroup?->name,
                    $snapshot->branch?->name,
                    number_format((float) $snapshot->current, 2, '.', ''),
                    number_format((float) $snapshot->bucket_30, 2, '.', ''),
                    number_format((float) $snapshot->bucket_60, 2, '.', ''),
                    number_format((float) $snapshot->bucket_90, 2, '.', ''),
                    number_format((float) $snapshot->bucket_over_90, 2, '.', ''),
                    number_format((float) $snapshot->total_outstanding, 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
