<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\AccountMapping;
use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\OrganizationEntity;
use App\Services\Accounting\AccountResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private ChartOfAccount $globalAccount;

    private ChartOfAccount $branchAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::query()->create([
            'name' => 'Test Branch',
            'code' => 'TST',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->globalAccount = ChartOfAccount::query()->create(['code' => '1000', 'name' => 'Global Cash', 'type' => 'asset']);
        $this->branchAccount = ChartOfAccount::query()->create(['code' => '1001', 'name' => 'Branch Cash', 'type' => 'asset']);
    }

    public function test_branch_specific_mapping_outranks_global_mapping(): void
    {
        AccountMapping::query()->create([
            'mapping_key' => 'cash_on_hand',
            'account_id' => $this->globalAccount->id,
            'status' => 'active',
        ]);

        AccountMapping::query()->create([
            'mapping_key' => 'cash_on_hand',
            'account_id' => $this->branchAccount->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);

        $resolved = app(AccountResolverService::class)->resolveByMappingKey('cash_on_hand', [
            'branch_id' => $this->branch->id,
        ]);

        $this->assertSame($this->branchAccount->id, $resolved?->id);
    }

    public function test_global_mapping_is_used_when_no_branch_specific_mapping_exists(): void
    {
        AccountMapping::query()->create([
            'mapping_key' => 'cash_on_hand',
            'account_id' => $this->globalAccount->id,
            'status' => 'active',
        ]);

        $resolved = app(AccountResolverService::class)->resolveByMappingKey('cash_on_hand', [
            'branch_id' => $this->branch->id,
        ]);

        $this->assertSame($this->globalAccount->id, $resolved?->id);
    }

    public function test_effective_dated_mapping_outside_range_is_excluded(): void
    {
        AccountMapping::query()->create([
            'mapping_key' => 'cash_on_hand',
            'account_id' => $this->globalAccount->id,
            'status' => 'active',
            'effective_to' => '2025-12-31',
        ]);

        AccountMapping::query()->create([
            'mapping_key' => 'cash_on_hand',
            'account_id' => $this->branchAccount->id,
            'status' => 'active',
            'effective_from' => '2026-01-01',
        ]);

        $resolved = app(AccountResolverService::class)->resolveByMappingKey('cash_on_hand', [
            'date' => '2026-06-15',
        ]);

        $this->assertSame($this->branchAccount->id, $resolved?->id);
    }

    public function test_lower_priority_value_wins_among_equally_specific_mappings(): void
    {
        AccountMapping::query()->create([
            'mapping_key' => 'cash_on_hand',
            'account_id' => $this->globalAccount->id,
            'status' => 'active',
            'priority' => 200,
        ]);

        AccountMapping::query()->create([
            'mapping_key' => 'cash_on_hand',
            'account_id' => $this->branchAccount->id,
            'status' => 'active',
            'priority' => 50,
        ]);

        $resolved = app(AccountResolverService::class)->resolveByMappingKey('cash_on_hand');

        $this->assertSame($this->branchAccount->id, $resolved?->id);
    }

    public function test_inactive_mapping_is_ignored(): void
    {
        AccountMapping::query()->create([
            'mapping_key' => 'cash_on_hand',
            'account_id' => $this->globalAccount->id,
            'status' => 'inactive',
        ]);

        $resolved = app(AccountResolverService::class)->resolveByMappingKey('cash_on_hand');

        $this->assertNull($resolved);
    }

    public function test_mapping_to_a_non_postable_account_is_ignored(): void
    {
        $nonPostable = ChartOfAccount::query()->create([
            'code' => '1002',
            'name' => 'Group Account',
            'type' => 'asset',
            'is_postable' => false,
        ]);

        AccountMapping::query()->create([
            'mapping_key' => 'cash_on_hand',
            'account_id' => $nonPostable->id,
            'status' => 'active',
        ]);

        $resolved = app(AccountResolverService::class)->resolveByMappingKey('cash_on_hand');

        $this->assertNull($resolved);
    }

    public function test_legal_entity_scoped_mapping_is_only_used_for_that_entity(): void
    {
        $entityA = OrganizationEntity::query()->create([
            'legal_name' => 'Entity A',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);
        $entityB = OrganizationEntity::query()->create([
            'legal_name' => 'Entity B',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        $entityAccount = ChartOfAccount::query()->create([
            'code' => '1100-A',
            'name' => 'Entity A Cash',
            'type' => 'asset',
        ]);

        AccountMapping::query()->create([
            'mapping_key' => 'cash_on_hand',
            'account_id' => $this->globalAccount->id,
            'status' => 'active',
        ]);
        AccountMapping::query()->create([
            'mapping_key' => 'cash_on_hand',
            'account_id' => $entityAccount->id,
            'legal_entity_id' => $entityA->id,
            'status' => 'active',
        ]);

        $resolver = app(AccountResolverService::class);

        $this->assertSame(
            $entityAccount->id,
            $resolver->resolveByMappingKey('cash_on_hand', ['legal_entity_id' => $entityA->id])?->id,
        );
        $this->assertSame(
            $this->globalAccount->id,
            $resolver->resolveByMappingKey('cash_on_hand', ['legal_entity_id' => $entityB->id])?->id,
        );
        $this->assertSame(
            $this->globalAccount->id,
            $resolver->resolveByMappingKey('cash_on_hand')?->id,
        );
    }

    public function test_category_scoped_mapping_does_not_leak_into_other_lookups(): void
    {
        $category = Category::query()->create([
            'name' => 'Electronics',
            'slug' => 'electronics-resolver',
            'is_active' => true,
        ]);
        $otherCategory = Category::query()->create([
            'name' => 'Grocery',
            'slug' => 'grocery-resolver',
            'is_active' => true,
        ]);

        $categoryAccount = ChartOfAccount::query()->create([
            'code' => '4001-C',
            'name' => 'Electronics Revenue',
            'type' => 'revenue',
        ]);

        AccountMapping::query()->create([
            'mapping_key' => 'sales_revenue',
            'account_id' => $this->globalAccount->id,
            'status' => 'active',
        ]);
        AccountMapping::query()->create([
            'mapping_key' => 'sales_revenue',
            'account_id' => $categoryAccount->id,
            'product_category_id' => $category->id,
            'status' => 'active',
            'priority' => 10,
        ]);

        $resolver = app(AccountResolverService::class);

        $this->assertSame(
            $categoryAccount->id,
            $resolver->resolveByMappingKey('sales_revenue', ['product_category_id' => $category->id])?->id,
        );
        $this->assertSame(
            $this->globalAccount->id,
            $resolver->resolveByMappingKey('sales_revenue', ['product_category_id' => $otherCategory->id])?->id,
        );
        $this->assertSame(
            $this->globalAccount->id,
            $resolver->resolveByMappingKey('sales_revenue')?->id,
        );
    }
}
