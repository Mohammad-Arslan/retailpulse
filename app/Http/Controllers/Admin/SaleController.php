<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Support\BranchContext;
use App\Support\ListPagination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Sale::class);

        $context = app(BranchContext::class);
        $filters = ListPagination::filters(
            $request,
            ['search', 'status', 'include_historical', 'sort', 'direction'],
        );

        $query = Sale::query()
            ->with(['cashier:id,name', 'branch:id,name', 'customer:id,name', 'invoice:id,sale_id,number'])
            ->when($context->branchId !== null, fn ($q) => $q->where('branch_id', $context->branchId))
            ->when(
                $context->branchId === null && $context->accessibleBranchIds !== null,
                fn ($q) => $q->whereIn('branch_id', $context->accessibleBranchIds),
            )
            ->when(
                ! filter_var($filters['include_historical'] ?? false, FILTER_VALIDATE_BOOLEAN),
                fn ($q) => $q->where('is_historical', false),
            )
            ->when(
                filled($filters['status'] ?? null),
                fn ($q) => $q->where('status', $filters['status']),
            )
            ->when(filled($filters['search'] ?? null), function ($q) use ($filters) {
                $search = (string) $filters['search'];
                $q->where(function ($inner) use ($search) {
                    $inner->where('id', 'like', "%{$search}%")
                        ->orWhereHas('invoice', fn ($invoice) => $invoice->where('number', 'like', "%{$search}%"))
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
                });
            });

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $direction = (string) ($filters['direction'] ?? 'desc');
        $allowedSorts = ['created_at', 'completed_at', 'grand_total', 'status'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $sales = $query
            ->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc')
            ->paginate(ListPagination::resolve($filters['per_page']))
            ->withQueryString()
            ->through(fn (Sale $sale): array => [
                'id' => $sale->id,
                'status' => $sale->status?->value,
                'grand_total' => number_format((float) $sale->grand_total, 2, '.', ''),
                'currency' => $sale->currency,
                'is_historical' => $sale->is_historical,
                'completed_at' => $sale->completed_at?->toIso8601String(),
                'created_at' => $sale->created_at?->toIso8601String(),
                'cashier' => $sale->cashier ? ['name' => $sale->cashier->name] : null,
                'branch' => $sale->branch ? ['name' => $sale->branch->name] : null,
                'customer' => $sale->customer ? ['name' => $sale->customer->name] : null,
                'invoice' => $sale->invoice ? ['number' => $sale->invoice->number] : null,
            ]);

        return Inertia::render('Admin/Sales/Index', [
            'sales' => $sales,
            'filters' => $filters,
            'statuses' => ['completed', 'pending_payment', 'partially_paid', 'voided', 'refunded'],
        ]);
    }

    public function show(Request $request, int $sale): Response
    {
        $record = Sale::query()
            ->with(['items', 'payments', 'invoice', 'customer', 'cashier', 'branch'])
            ->findOrFail($sale);

        $this->authorize('view', $record);

        return Inertia::render('Admin/Sales/Show', [
            'sale' => [
                'id' => $record->id,
                'status' => $record->status?->value,
                'subtotal' => number_format((float) $record->subtotal, 2, '.', ''),
                'total_discount' => number_format((float) $record->total_discount, 2, '.', ''),
                'tax_total' => number_format((float) $record->tax_total, 2, '.', ''),
                'grand_total' => number_format((float) $record->grand_total, 2, '.', ''),
                'balance_due' => number_format((float) $record->balance_due, 2, '.', ''),
                'currency' => $record->currency,
                'is_historical' => $record->is_historical,
                'notes' => $record->notes,
                'completed_at' => $record->completed_at?->toIso8601String(),
                'created_at' => $record->created_at?->toIso8601String(),
                'cashier' => $record->cashier ? ['name' => $record->cashier->name] : null,
                'branch' => $record->branch ? ['name' => $record->branch->name] : null,
                'customer' => $record->customer ? [
                    'id' => $record->customer->id,
                    'name' => $record->customer->name,
                    'phone' => $record->customer->phone,
                    'email' => $record->customer->email,
                ] : null,
                'items' => $record->items->map(fn ($item) => [
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                    'tax_amount' => number_format((float) $item->tax_amount, 2, '.', ''),
                    'line_total_inc_tax' => number_format((float) $item->line_total_inc_tax, 2, '.', ''),
                ])->all(),
                'payments' => $record->payments->map(fn ($payment) => [
                    'method' => $payment->method?->value,
                    'amount' => number_format((float) $payment->amount, 2, '.', ''),
                    'status' => $payment->status?->value,
                    'meta' => $payment->meta,
                    'created_at' => $payment->created_at?->toIso8601String(),
                ])->all(),
                'invoice' => $record->invoice ? [
                    'number' => $record->invoice->number,
                    'public_token' => $record->invoice->public_token,
                    'fbr_status' => $record->invoice->fbr_status?->value,
                    'pdf_path' => $record->invoice->pdf_path,
                ] : null,
            ],
        ]);
    }
}
