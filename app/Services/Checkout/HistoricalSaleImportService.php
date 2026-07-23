<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Enums\FbrInvoiceStatus;
use App\Enums\SaleStatus;
use App\Enums\TaxMode;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\SaleInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class HistoricalSaleImportService
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int, errors: list<array{row: int, reason: string}>}
     */
    public function import(array $rows, int $importedBy): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;

            $validation = $this->validateRow($row, $rowNum);
            if ($validation !== null) {
                $errors[] = $validation;
                $skipped++;

                continue;
            }

            // Skip duplicate invoice numbers within the same branch
            $invoiceNumber = (string) ($row['invoice_number'] ?? '');
            $branchId = (int) $row['branch_id'];

            if ($invoiceNumber !== '' && SaleInvoice::query()
                ->whereHas('sale', fn ($q) => $q->where('branch_id', $branchId))
                ->where('number', $invoiceNumber)
                ->exists()) {
                $errors[] = ['row' => $rowNum, 'reason' => "Duplicate invoice number '{$invoiceNumber}' for branch {$branchId}."];
                $skipped++;

                continue;
            }

            try {
                DB::transaction(function () use ($row, $importedBy, $invoiceNumber) {
                    $taxMode = TaxMode::tryFrom((string) ($row['tax_mode'] ?? 'exclusive')) ?? TaxMode::Exclusive;

                    $sale = Sale::query()->create([
                        'cart_id' => null,
                        'branch_id' => (int) $row['branch_id'],
                        'warehouse_id' => isset($row['warehouse_id']) ? (int) $row['warehouse_id'] : null,
                        'customer_id' => isset($row['customer_id']) ? (int) $row['customer_id'] : null,
                        'cashier_id' => isset($row['cashier_id']) ? (int) $row['cashier_id'] : $importedBy,
                        'status' => SaleStatus::Completed,
                        'subtotal' => round((float) ($row['subtotal'] ?? 0), 2),
                        'total_discount' => round((float) ($row['total_discount'] ?? 0), 2),
                        'tax_total' => round((float) ($row['tax_total'] ?? 0), 2),
                        'grand_total' => round((float) ($row['grand_total'] ?? 0), 2),
                        'balance_due' => 0,
                        'currency' => (string) ($row['currency'] ?? 'PKR'),
                        'tax_mode' => $taxMode,
                        'notes' => isset($row['notes']) ? (string) $row['notes'] : null,
                        'is_historical' => true,
                        'completed_at' => $row['sale_date'],
                        'created_at' => $row['sale_date'],
                    ]);

                    foreach ((array) ($row['items'] ?? []) as $item) {
                        $sale->items()->create([
                            'product_id' => (int) $item['product_id'],
                            'product_variant_id' => isset($item['variant_id']) ? (int) $item['variant_id'] : null,
                            'sku' => (string) ($item['sku'] ?? ''),
                            'name' => (string) ($item['name'] ?? ''),
                            'unit_price' => round((float) ($item['unit_price'] ?? 0), 2),
                            'quantity' => (int) ($item['quantity'] ?? 1),
                            'discount_type' => $item['discount_type'] ?? null,
                            'discount_value' => isset($item['discount_value']) ? round((float) $item['discount_value'], 2) : null,
                            'line_total' => round((float) ($item['line_total'] ?? 0), 2),
                            'tax_rate' => round((float) ($item['tax_rate'] ?? 0), 4),
                            'tax_amount' => round((float) ($item['tax_amount'] ?? 0), 2),
                            'line_total_inc_tax' => round((float) ($item['line_total_inc_tax'] ?? 0), 2),
                        ]);
                    }

                    SaleInvoice::query()->create([
                        'sale_id' => $sale->id,
                        'number' => $invoiceNumber !== '' ? $invoiceNumber : 'HIST-'.$sale->id,
                        'template' => 'a4',
                        'pdf_path' => null,
                        'public_token' => (string) Str::uuid(),
                        'fbr_status' => FbrInvoiceStatus::NotApplicable,
                        'fbr_invoice_number' => null,
                    ]);
                });
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'reason' => 'Database error: '.$e->getMessage()];
                $skipped++;
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{row: int, reason: string}|null
     */
    private function validateRow(array $row, int $rowNum): ?array
    {
        if (empty($row['branch_id'])) {
            return ['row' => $rowNum, 'reason' => 'Missing required field: branch_id.'];
        }

        if (! Branch::query()->where('id', (int) $row['branch_id'])->exists()) {
            return ['row' => $rowNum, 'reason' => "Branch ID {$row['branch_id']} does not exist."];
        }

        if (empty($row['sale_date'])) {
            return ['row' => $rowNum, 'reason' => 'Missing required field: sale_date.'];
        }

        try {
            $date = Carbon::parse((string) $row['sale_date']);
            if ($date->isFuture()) {
                return ['row' => $rowNum, 'reason' => 'sale_date must be in the past.'];
            }
        } catch (\Throwable) {
            return ['row' => $rowNum, 'reason' => 'Invalid sale_date format.'];
        }

        if (empty($row['items'])) {
            return ['row' => $rowNum, 'reason' => 'At least one item is required.'];
        }

        return null;
    }
}
