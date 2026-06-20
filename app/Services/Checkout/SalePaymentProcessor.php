<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\PaymentGatewayConfig;
use App\Models\Sale;
use Illuminate\Support\Str;

final class SalePaymentProcessor
{
    /**
     * @param  array<string, mixed>  $meta
     * @return array{
     *     status: PaymentStatus,
     *     gateway_reference: ?string,
     *     meta: array<string, mixed>,
     *     gateway_response: ?array<string, mixed>
     * }
     */
    public function process(Sale $sale, PaymentMethod $method, float $amount, array $meta = []): array
    {
        if (! $method->requiresGateway()) {
            return [
                'status' => PaymentStatus::Completed,
                'gateway_reference' => null,
                'meta' => $meta,
                'gateway_response' => null,
            ];
        }

        $gateway = match ($method) {
            PaymentMethod::Card => 'stripe',
            PaymentMethod::MobileWallet => 'jazzcash',
            default => null,
        };

        $config = PaymentGatewayConfig::query()
            ->where('gateway', $gateway)
            ->where(function ($query) use ($sale) {
                $query->where('branch_id', $sale->branch_id)->orWhereNull('branch_id');
            })
            ->orderByRaw('branch_id IS NULL')
            ->first();

        $mode = $config?->mode ?? 'stub';

        if ($mode === 'disabled') {
            return [
                'status' => PaymentStatus::Failed,
                'gateway_reference' => null,
                'meta' => $meta,
                'gateway_response' => ['error' => 'Payment method is disabled for this branch.'],
            ];
        }

        if ($mode === 'stub') {
            return [
                'status' => PaymentStatus::Completed,
                'gateway_reference' => 'STUB-'.Str::uuid()->toString(),
                'meta' => $meta,
                'gateway_response' => ['mode' => 'stub', 'success' => true],
            ];
        }

        return [
            'status' => PaymentStatus::Failed,
            'gateway_reference' => null,
            'meta' => $meta,
            'gateway_response' => ['error' => 'Live gateway not configured.'],
        ];
    }

    public function userFriendlyError(?array $gatewayResponse): string
    {
        if ($gatewayResponse === null) {
            return __('Payment could not be processed. Please try again.');
        }

        return (string) ($gatewayResponse['message'] ?? $gatewayResponse['error'] ?? __('Payment could not be processed. Please try again.'));
    }
}
