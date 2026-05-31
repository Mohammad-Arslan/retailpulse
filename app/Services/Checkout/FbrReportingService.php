<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Sale;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class FbrReportingService
{
    /**
     * @return array{success: bool, invoice_number: ?string, error: ?string}
     */
    public function submit(Sale $sale): array
    {
        $endpoint = (string) SystemSetting::get('fbr', 'iris_endpoint', '');
        $userId = (string) SystemSetting::get('fbr', 'user_id', '');
        $password = SystemSetting::getEncrypted('fbr', 'password');

        if ($endpoint === '') {
            return ['success' => false, 'invoice_number' => null, 'error' => 'FBR endpoint not configured.'];
        }

        if ($userId === '' || $password === null) {
            return ['success' => false, 'invoice_number' => null, 'error' => 'FBR credentials not configured.'];
        }

        $payload = $this->buildPayload($sale);

        try {
            $response = Http::withBasicAuth($userId, $password)
                ->timeout((int) SystemSetting::get('fbr', 'api_timeout_sec', 10))
                ->post($endpoint, $payload);

            if ($response->failed()) {
                $error = $response->json('ErrorMessage') ?? $response->body();
                Log::warning('FBR IRIS call failed.', ['status' => $response->status(), 'body' => $response->body()]);

                return ['success' => false, 'invoice_number' => null, 'error' => (string) $error];
            }

            $body = $response->json();
            $invoiceNumber = $body['InvoiceNumber'] ?? $body['FBRInvoiceNumber'] ?? null;

            return ['success' => true, 'invoice_number' => (string) $invoiceNumber, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('FBR IRIS request threw exception.', ['message' => $e->getMessage()]);

            return ['success' => false, 'invoice_number' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(Sale $sale): array
    {
        $sale->load('items');

        return [
            'InvoiceNumber' => $sale->invoice?->number,
            'DateTime' => ($sale->completed_at ?? now())->utc()->toIso8601String(),
            'TotalBillAmount' => (float) $sale->grand_total,
            'TotalTaxCharged' => (float) $sale->tax_total,
            'Discount' => (float) $sale->total_discount,
            'FurtherTax' => 0,
            'InvoiceType' => 'SI',
            'PaymentMode' => 1,
            'Items' => $sale->items->map(fn ($item) => [
                'ItemCode' => $item->sku,
                'ItemName' => $item->name,
                'Quantity' => $item->quantity,
                'TaxRate' => round((float) $item->tax_rate * 100, 2),
                'SaleValue' => (float) $item->line_total,
                'TaxCharged' => (float) $item->tax_amount,
                'Discount' => 0,
                'InvoiceValue' => (float) $item->line_total_inc_tax,
            ])->all(),
        ];
    }
}
