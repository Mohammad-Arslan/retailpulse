<?php

declare(strict_types=1);

namespace Tests\Unit\Loyalty;

use App\Enums\LoyaltyRuleType;
use App\Enums\ProductType;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyRule;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\Loyalty\LoyaltyRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsLoyaltyEngine;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class LoyaltyRuleEngineTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLoyaltyEngine;
    use SeedsRbac;

    private LoyaltyRuleEngine $engine;

    private LoyaltyProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->program = $this->seedLoyaltyEngine();
        $this->engine = app(LoyaltyRuleEngine::class);
    }

    public function test_spend_based_rule_calculates_points(): void
    {
        LoyaltyRule::query()->where('program_id', $this->program->id)->delete();
        $this->addSpendRule($this->program, 100, 1);

        $sale = $this->makeSale(grandTotal: 500.00);

        $result = $this->engine->evaluateForSale($sale, $this->program);

        $this->assertSame(5, $result['points']);
    }

    public function test_first_purchase_bonus_is_applied(): void
    {
        LoyaltyRule::query()->create([
            'program_id' => $this->program->id,
            'name' => 'First Purchase',
            'rule_type' => LoyaltyRuleType::FirstPurchase,
            'priority' => 5,
            'conditions_json' => [],
            'reward_json' => ['bonus_points' => 100],
            'is_active' => true,
        ]);

        $sale = $this->makeSale(grandTotal: 200.00);

        $result = $this->engine->evaluateForSale($sale, $this->program);

        $this->assertGreaterThanOrEqual(100, $result['points']);
    }

    public function test_rules_execute_by_priority_order(): void
    {
        LoyaltyRule::query()->where('program_id', $this->program->id)->delete();

        LoyaltyRule::query()->create([
            'program_id' => $this->program->id,
            'name' => 'Low Priority',
            'rule_type' => LoyaltyRuleType::SpendBased,
            'priority' => 50,
            'conditions_json' => ['spend_amount' => 100],
            'reward_json' => ['points' => 1],
            'is_active' => true,
        ]);

        LoyaltyRule::query()->create([
            'program_id' => $this->program->id,
            'name' => 'High Priority Bonus',
            'rule_type' => LoyaltyRuleType::ManualBonus,
            'priority' => 1,
            'conditions_json' => [],
            'reward_json' => [],
            'is_active' => false,
        ]);

        $sale = $this->makeSale(grandTotal: 100.00);
        $result = $this->engine->evaluateForSale($sale, $this->program);

        $this->assertSame(1, $result['points']);
    }

    private function makeSale(float $grandTotal): Sale
    {
        $branch = Branch::query()->create([
            'name' => 'Loyalty Branch',
            'code' => 'LYL',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Loyalty Customer',
            'phone' => '03001234567',
            'is_active' => true,
        ]);

        $cashier = User::factory()->create(['is_active' => true]);

        $sale = Sale::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'cashier_id' => $cashier->id,
            'status' => SaleStatus::Completed,
            'subtotal' => $grandTotal,
            'grand_total' => $grandTotal,
            'balance_due' => 0,
            'completed_at' => now(),
        ]);

        $unit = Unit::query()->create(['name' => 'Piece', 'abbreviation' => 'pc', 'is_active' => true]);
        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Test',
            'slug' => 'test-loyalty',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-LYL',
            'sell_price' => $grandTotal,
            'is_default' => true,
        ]);

        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'sku' => 'SKU-LYL',
            'name' => 'Test',
            'unit_price' => $grandTotal,
            'quantity' => 1,
            'line_total' => $grandTotal,
            'line_total_inc_tax' => $grandTotal,
        ]);

        return $sale->fresh(['items.product', 'customer']);
    }
}
