<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\DTOs\Checkout\AddPaymentData;
use App\DTOs\Checkout\ConfirmCheckoutData;
use App\DTOs\Inventory\DeductStockData;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PosCartStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementReason;
use App\Enums\TaxMode;
use App\Events\SaleCompleted;
use App\Jobs\SubmitFbrInvoiceJob;
use App\Models\Customer;
use App\Models\PosCart;
use App\Models\PosCartItem;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Contracts\PosCartRepositoryInterface;
use App\Services\Customer\CustomerCreditService;
use App\Services\Customer\StoreCreditService;
use App\Services\Customer\WalletService;
use App\Services\InventoryService;
use App\Services\Loyalty\CheckoutLoyaltyService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CheckoutService
{
    public function __construct(
        private readonly PosCartRepositoryInterface $carts,
        private readonly CheckoutConfigService $config,
        private readonly TaxCalculationService $tax,
        private readonly SalePaymentProcessor $payments,
        private readonly InvoiceService $invoices,
        private readonly InventoryService $inventory,
        private readonly WalletService $wallet,
        private readonly StoreCreditService $storeCredit,
        private readonly CustomerCreditService $customerCredit,
        private readonly CheckoutLoyaltyService $checkoutLoyalty,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function bootstrap(PosCart $cart): array
    {
        if ($cart->status !== PosCartStatus::Completing) {
            throw ValidationException::withMessages([
                'status' => __('Cart is not ready for checkout.'),
            ]);
        }

        $cart->load(['items', 'cashier', 'branch']);
        $settings = $this->config->resolve($cart->branch_id);
        $taxMode = TaxMode::tryFrom($settings['tax_mode']) ?? TaxMode::Exclusive;

        $items = $this->buildTaxedCartItems($cart->items, $taxMode);

        $subtotal = collect($items)->sum(fn ($i) => (float) $i['line_total']);
        $taxTotal = collect($items)->sum(fn ($i) => (float) $i['tax_amount']);
        $grandTotal = collect($items)->sum(fn ($i) => (float) $i['line_total_inc_tax']);
        $grossSubtotal = $cart->items->sum(fn (PosCartItem $i) => (float) $i->unit_price * $i->quantity);

        $existingSale = Sale::query()->where('cart_id', $cart->id)->first();

        return [
            'cart_id' => $cart->id,
            'cashier_id' => $cart->cashier_id,
            'branch_id' => $cart->branch_id,
            'sale_id' => $existingSale?->id,
            'sale_status' => $existingSale?->status?->value,
            'balance_due' => $existingSale !== null
                ? number_format((float) $existingSale->balance_due, 2, '.', '')
                : number_format($grandTotal, 2, '.', ''),
            'items' => $items,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'total_discount' => number_format(max(0, $grossSubtotal - $subtotal), 2, '.', ''),
            'tax_total' => number_format($taxTotal, 2, '.', ''),
            'grand_total' => number_format($grandTotal, 2, '.', ''),
            'currency' => $settings['currency'],
            'notes' => $cart->notes,
            'customer' => $existingSale?->customer_id,
            'loyalty_enabled' => (bool) SystemSetting::get('loyalty', 'enabled', true),
            'config' => $settings,
        ];
    }

    public function confirm(PosCart $cart, ConfirmCheckoutData $data, int $cashierId): Sale
    {
        if ($cart->status !== PosCartStatus::Completing) {
            throw ValidationException::withMessages([
                'status' => __('Cart is not ready for checkout.'),
            ]);
        }

        $existing = Sale::query()->where('cart_id', $cart->id)->first();
        if ($existing !== null) {
            return $existing->load(['items', 'payments']);
        }

        return DB::transaction(function () use ($cart, $data, $cashierId) {
            $cart->load('items');
            $settings = $this->config->resolve($cart->branch_id);
            $taxMode = TaxMode::tryFrom($settings['tax_mode']) ?? TaxMode::Exclusive;
            $warehouseId = $this->defaultWarehouseForBranch($cart->branch_id);

            $taxedItems = $this->buildTaxedCartItems($cart->items, $taxMode, asSaleRows: true);

            $saleItems = [];
            $taxTotal = 0.0;
            $subtotal = 0.0;
            $grandTotal = 0.0;
            $grossSubtotal = 0.0;

            foreach ($taxedItems as $row) {
                $saleItems[] = [
                    'product_id' => $row['product_id'],
                    'product_variant_id' => $row['variant_id'],
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'unit_price' => $row['unit_price_raw'],
                    'quantity' => $row['quantity'],
                    'discount_type' => $row['discount_type'],
                    'discount_value' => $row['discount_value_raw'],
                    'line_total' => $row['line_total_raw'],
                    'tax_rate' => $row['tax_rate_raw'],
                    'tax_amount' => $row['tax_amount_raw'],
                    'line_total_inc_tax' => $row['line_total_inc_tax_raw'],
                ];

                $subtotal += $row['line_total_raw'];
                $taxTotal += $row['tax_amount_raw'];
                $grandTotal += $row['line_total_inc_tax_raw'];
                $grossSubtotal += $row['gross_line_total'];
            }

            $sale = Sale::query()->create([
                'cart_id' => $cart->id,
                'branch_id' => $cart->branch_id,
                'warehouse_id' => $warehouseId,
                'customer_id' => $data->customerId,
                'cashier_id' => $cashierId,
                'status' => SaleStatus::PendingPayment,
                'subtotal' => round($subtotal, 2),
                'total_discount' => round(max(0, $grossSubtotal - $subtotal), 2),
                'tax_total' => round($taxTotal, 2),
                'grand_total' => round($grandTotal, 2),
                'balance_due' => round($grandTotal, 2),
                'currency' => $settings['currency'],
                'tax_mode' => $taxMode,
                'notes' => $data->notes ?? $cart->notes,
            ]);

            foreach ($saleItems as $row) {
                $sale->items()->create($row);
            }

            if ($data->loyaltyPointsToRedeem > 0) {
                $this->checkoutLoyalty->applyRedemptionToSale($sale, $data->loyaltyPointsToRedeem, $cashierId);
                $sale->refresh();
            }

            $this->carts->update($cart, [
                'status' => PosCartStatus::Completed,
                'completed_at' => now(),
            ]);

            return $sale->load(['items', 'payments']);
        });
    }

    public function addPayment(Sale $sale, AddPaymentData $data, int $cashierId): Sale
    {
        if ($sale->status->isImmutable()) {
            throw ValidationException::withMessages([
                'status' => __('This sale cannot accept additional payments.'),
            ]);
        }

        if (! $sale->status->isPayable()) {
            throw ValidationException::withMessages([
                'status' => __('This sale is not accepting payments.'),
            ]);
        }

        $method = PaymentMethod::tryFrom($data->method);
        if ($method === null) {
            throw ValidationException::withMessages([
                'method' => __('Invalid payment method.'),
            ]);
        }

        if ($method->requiresCustomer() && $sale->customer_id === null) {
            throw ValidationException::withMessages([
                'customer_id' => __('A customer is required for this payment method.'),
            ]);
        }

        if ($method === PaymentMethod::Wallet) {
            $walletBalance = $this->wallet->getAvailableBalance($sale->customer_id);
            if ($walletBalance <= 0) {
                throw ValidationException::withMessages([
                    'wallet' => __('Customer wallet has no available balance.'),
                ]);
            }
        }

        if ($method === PaymentMethod::StoreCredit) {
            $storeCreditBalance = $this->storeCredit->getAvailableBalance($sale->customer_id);
            if ($storeCreditBalance <= 0) {
                throw ValidationException::withMessages([
                    'store_credit' => __('Customer has no available store credit.'),
                ]);
            }
        }

        $settings = $this->config->resolve($sale->branch_id);
        $balanceDue = (float) $sale->balance_due;

        $paymentAmount = min($data->amount > 0 ? $data->amount : $balanceDue, $balanceDue);

        if ($method === PaymentMethod::Wallet) {
            $paymentAmount = min($paymentAmount, $this->wallet->getAvailableBalance($sale->customer_id));
        }

        if ($method === PaymentMethod::StoreCredit) {
            $paymentAmount = min($paymentAmount, $this->storeCredit->getAvailableBalance($sale->customer_id));
        }

        if ($method === PaymentMethod::Credit) {
            $customer = Customer::query()->findOrFail($sale->customer_id);
            $cashier = User::query()->findOrFail($cashierId);
            $this->customerCredit->assertCanChargeCredit(
                customer: $customer,
                amount: $paymentAmount,
                branchId: $sale->branch_id,
                managerPin: $data->managerPin,
                cashier: $cashier,
            );
        }

        if ($method === PaymentMethod::Cash && $data->tenderedAmount !== null) {
            if ($data->tenderedAmount < $paymentAmount) {
                throw ValidationException::withMessages([
                    'tendered_amount' => __('Tendered amount must be at least the amount being paid.'),
                ]);
            }
        }

        if (! $settings['split_tender_enabled'] && $paymentAmount < $balanceDue) {
            $layawayEnabled = (bool) $settings['layaway_enabled'];
            if (! $layawayEnabled) {
                throw ValidationException::withMessages([
                    'amount' => __('Full payment is required.'),
                ]);
            }

            $minDepositPercent = (float) $settings['layaway_min_deposit_percent'];
            if ($minDepositPercent > 0) {
                $minDeposit = round((float) $sale->grand_total * ($minDepositPercent / 100), 2);
                if ($paymentAmount < $minDeposit) {
                    throw ValidationException::withMessages([
                        'amount' => __('Minimum deposit of :amount is required.', [
                            'amount' => number_format($minDeposit, 2),
                        ]),
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($sale, $data, $cashierId, $method, $settings, $paymentAmount, $balanceDue) {
            $meta = $data->meta;

            if ($method === PaymentMethod::Wallet) {
                $this->wallet->debitForCheckout(
                    customerId: $sale->customer_id,
                    amount: $paymentAmount,
                    saleId: $sale->id,
                    userId: $cashierId,
                );
            }

            if ($method === PaymentMethod::StoreCredit) {
                $this->storeCredit->redeemForCheckout(
                    customerId: $sale->customer_id,
                    amount: $paymentAmount,
                    saleId: $sale->id,
                    userId: $cashierId,
                );
            }

            if ($method === PaymentMethod::Cash && $data->tenderedAmount !== null) {
                $meta['tendered_amount'] = $data->tenderedAmount;
                $changeDue = $data->tenderedAmount - $paymentAmount;
                if ($settings['change_rounding_mode'] === 'nearest_5') {
                    $changeDue = round($changeDue / 5) * 5;
                }
                $meta['change_due'] = round($changeDue, 2);
            }

            $gatewayResult = $this->payments->process($sale, $method, $paymentAmount, $meta);

            $payment = SalePayment::query()->create([
                'sale_id' => $sale->id,
                'cashier_id' => $cashierId,
                'method' => $method,
                'amount' => $paymentAmount,
                'status' => $gatewayResult['status'],
                'gateway_reference' => $gatewayResult['gateway_reference'] ?? null,
                'meta' => $gatewayResult['meta'] ?? $meta,
                'gateway_response' => $gatewayResult['gateway_response'] ?? null,
                'created_at' => now(),
            ]);

            if ($payment->status !== PaymentStatus::Completed) {
                return $sale->fresh(['items', 'payments']);
            }

            if ($settings['inventory_deduct_on'] === 'payment_started' && $sale->payments()->where('status', PaymentStatus::Completed)->count() === 1) {
                $this->deductInventory($sale, $cashierId);
            }

            $newBalance = round($balanceDue - $paymentAmount, 2);
            $sale->balance_due = max(0, $newBalance);

            if ($newBalance > 0) {
                $sale->status = SaleStatus::PartiallyPaid;
                $sale->save();

                return $sale->fresh(['items', 'payments', 'invoice']);
            }

            return $this->finalizeSale($sale, $cashierId);
        });
    }

    public function voidSale(Sale $sale): void
    {
        if ($sale->status->isImmutable()) {
            throw ValidationException::withMessages([
                'status' => __('Completed sales cannot be voided from checkout.'),
            ]);
        }

        if (! $sale->status->canTransitionTo(SaleStatus::Voided)) {
            throw ValidationException::withMessages([
                'status' => __('This sale cannot be voided.'),
            ]);
        }

        $completedPayments = $sale->payments()
            ->where('status', PaymentStatus::Completed)
            ->exists();

        if ($completedPayments) {
            throw ValidationException::withMessages([
                'payments' => __('Completed payments must be reversed before voiding.'),
            ]);
        }

        DB::transaction(function () use ($sale) {
            $sale->payments()->where('status', PaymentStatus::Pending)->delete();

            $sale->update([
                'status' => SaleStatus::Voided,
                'voided_at' => now(),
                'balance_due' => 0,
            ]);

            if ($sale->cart_id !== null) {
                $cart = PosCart::query()->find($sale->cart_id);
                if ($cart !== null) {
                    $this->carts->update($cart, [
                        'status' => PosCartStatus::Voided,
                        'voided_at' => now(),
                    ]);
                }
            }

            $sale->delete();
        });
    }

    public function abandonCheckout(PosCart $cart): PosCart
    {
        if ($cart->status !== PosCartStatus::Completing) {
            throw ValidationException::withMessages([
                'status' => __('Only carts awaiting payment can be reopened.'),
            ]);
        }

        $hasSale = Sale::query()->where('cart_id', $cart->id)->exists();
        if ($hasSale) {
            throw ValidationException::withMessages([
                'sale' => __('Sale already confirmed. Void the sale to return to POS.'),
            ]);
        }

        return $this->carts->update($cart, [
            'status' => PosCartStatus::Active,
        ]);
    }

    private function finalizeSale(Sale $sale, int $cashierId): Sale
    {
        $settings = $this->config->resolve($sale->branch_id);
        $fbrEnabled = (bool) $settings['fbr_enabled'];
        $failureMode = (string) SystemSetting::get('fbr', 'failure_mode', 'queue');

        if ($fbrEnabled && $failureMode === 'block') {
            $fbrResult = app(FbrReportingService::class)->submit($sale);
            if (! $fbrResult['success']) {
                throw ValidationException::withMessages([
                    'fbr' => __('FBR reporting failed. Please retry or contact support.'),
                ]);
            }
        }

        if ($settings['inventory_deduct_on'] === 'sale_completed') {
            $this->deductInventory($sale, $cashierId);
        }

        $sale->update([
            'status' => SaleStatus::Completed,
            'balance_due' => 0,
            'completed_at' => now(),
        ]);

        $sale->load('payments');

        $creditTotal = (float) $sale->payments
            ->where('status', PaymentStatus::Completed)
            ->filter(fn (SalePayment $payment) => $payment->method === PaymentMethod::Credit)
            ->sum('amount');

        $invoice = $this->invoices->createForSale($sale);
        $sale->setRelation('invoice', $invoice);

        if ($creditTotal > 0 && $sale->customer_id !== null) {
            $this->customerCredit->recordCreditSale($sale, $creditTotal, $cashierId);
        }

        SaleCompleted::dispatch($sale);

        if ($fbrEnabled && $failureMode === 'queue') {
            SubmitFbrInvoiceJob::dispatch($invoice->id);
        }

        return $sale->fresh(['items', 'payments', 'invoice']);
    }

    private function defaultWarehouseForBranch(int $branchId): int
    {
        $warehouse = Warehouse::query()
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($warehouse === null) {
            $warehouse = Warehouse::query()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->first();
        }

        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'branch' => __('No active warehouse found for this branch.'),
            ]);
        }

        return $warehouse->id;
    }

    /**
     * @param  Collection<int, PosCartItem>  $cartItems
     * @return list<array<string, mixed>>
     */
    private function buildTaxedCartItems($cartItems, TaxMode $taxMode, bool $asSaleRows = false): array
    {
        $lines = $cartItems->map(fn (PosCartItem $item): array => [
            'line_total' => (float) $item->line_total,
            'product_id' => $item->product_id,
            'variant_id' => $item->product_variant_id,
        ])->all();

        $taxResults = $this->tax->isPerItem()
            ? array_map(
                fn (array $line): array => $this->tax->computeLineTax(
                    lineTotal: $line['line_total'],
                    productId: $line['product_id'],
                    variantId: $line['variant_id'],
                    taxMode: $taxMode,
                ),
                $lines,
            )
            : $this->tax->computeCartTax($lines, $taxMode);

        $items = [];

        foreach ($cartItems->values() as $index => $item) {
            $tax = $taxResults[$index];
            $lineTotal = (float) $item->line_total;

            $row = [
                'product_id' => $item->product_id,
                'variant_id' => $item->product_variant_id,
                'sku' => $item->sku,
                'name' => $item->name,
                'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                'quantity' => $item->quantity,
                'discount_type' => $item->discount_type,
                'discount_value' => $item->discount_value !== null
                    ? number_format((float) $item->discount_value, 2, '.', '')
                    : null,
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'tax_rate' => number_format($tax['tax_rate'], 4, '.', ''),
                'tax_amount' => number_format($tax['tax_amount'], 2, '.', ''),
                'line_total_inc_tax' => number_format($tax['line_total_inc_tax'], 2, '.', ''),
            ];

            if ($asSaleRows) {
                $row['unit_price_raw'] = $item->unit_price;
                $row['discount_value_raw'] = $item->discount_value;
                $row['line_total_raw'] = $lineTotal;
                $row['tax_rate_raw'] = $tax['tax_rate'];
                $row['tax_amount_raw'] = $tax['tax_amount'];
                $row['line_total_inc_tax_raw'] = $tax['line_total_inc_tax'];
                $row['gross_line_total'] = (float) $item->unit_price * $item->quantity;
            }

            $items[] = $row;
        }

        return $items;
    }

    private function deductInventory(Sale $sale, int $userId): void
    {
        if ($sale->is_historical || $sale->warehouse_id === null) {
            return;
        }

        $sale->load('items');

        foreach ($sale->items as $item) {
            if ($item->product_variant_id === null) {
                continue;
            }

            $this->inventory->deduct(new DeductStockData(
                warehouseId: $sale->warehouse_id,
                variantId: $item->product_variant_id,
                batchId: null,
                quantity: $item->quantity,
                reason: StockMovementReason::Sale,
                userId: $userId,
                referenceType: 'sale',
                referenceId: $sale->id,
            ));
        }
    }
}
