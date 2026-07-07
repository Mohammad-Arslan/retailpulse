<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\AccountMapping;
use App\Models\Branch;
use App\Models\ChartOfAccount;
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
}
