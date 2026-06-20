<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\CustomerArLedger;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class CustomerStatementService
{
    public function __construct(
        private readonly CustomerCreditService $credit,
    ) {}

    public function generate(Customer $customer, ?int $branchId = null): string
    {
        $ledgerQuery = CustomerArLedger::query()
            ->with(['branch', 'sale.invoice', 'user'])
            ->where('customer_id', $customer->id)
            ->orderBy('created_at')
            ->orderBy('id');

        if ($branchId !== null) {
            $ledgerQuery->where('branch_id', $branchId);
        }

        /** @var Collection<int, CustomerArLedger> $entries */
        $entries = $ledgerQuery->get();

        $outstanding = $this->credit->getOutstandingBalance($customer->id, $branchId);

        $pdf = Pdf::loadView('customers.statement', [
            'customer' => $customer,
            'entries' => $entries,
            'outstanding' => $outstanding,
            'company' => [
                'legal_name' => SystemSetting::get('company', 'legal_name', config('app.name')),
                'address' => SystemSetting::get('company', 'address', ''),
                'phone' => SystemSetting::get('company', 'phone', ''),
                'email' => SystemSetting::get('company', 'email', ''),
            ],
            'generatedAt' => now(),
        ]);

        $suffix = $branchId !== null ? "branch-{$branchId}" : 'all';
        $path = "statements/customer-{$customer->id}-{$suffix}-".now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
