<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\BranchAccountingProfile;
use App\Services\Accounting\BranchAccountingModuleGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountingModuleGateTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

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
    }

    public function test_branch_with_no_profile_row_defaults_to_core_only(): void
    {
        $gate = new BranchAccountingModuleGate;

        $this->assertTrue($gate->isEnabled('core', $this->branch->id));
        $this->assertFalse($gate->isEnabled('cost_centres', $this->branch->id));
    }

    public function test_branch_with_empty_module_list_defaults_to_core_only(): void
    {
        BranchAccountingProfile::query()->create([
            'branch_id' => $this->branch->id,
            'accounting_enabled_modules' => [],
        ]);

        $gate = new BranchAccountingModuleGate;

        $this->assertSame(['core'], $gate->enabledModules($this->branch->id));
    }

    public function test_core_is_always_enabled_even_if_not_explicitly_stored(): void
    {
        BranchAccountingProfile::query()->create([
            'branch_id' => $this->branch->id,
            'accounting_enabled_modules' => ['multi_currency'],
        ]);

        $gate = new BranchAccountingModuleGate;

        $this->assertTrue($gate->isEnabled('core', $this->branch->id));
        $this->assertTrue($gate->isEnabled('multi_currency', $this->branch->id));
    }

    public function test_module_disabled_when_stored_but_dependency_missing(): void
    {
        BranchAccountingProfile::query()->create([
            'branch_id' => $this->branch->id,
            'accounting_enabled_modules' => ['core', 'intercompany'],
        ]);

        $gate = new BranchAccountingModuleGate;

        $this->assertFalse($gate->isEnabled('intercompany', $this->branch->id));
    }

    public function test_module_enabled_when_stored_and_dependency_satisfied(): void
    {
        BranchAccountingProfile::query()->create([
            'branch_id' => $this->branch->id,
            'accounting_enabled_modules' => ['core', 'multi_currency', 'intercompany'],
        ]);

        $gate = new BranchAccountingModuleGate;

        $this->assertTrue($gate->isEnabled('intercompany', $this->branch->id));
    }

    public function test_credit_notes_requires_both_core_and_ar_ap(): void
    {
        BranchAccountingProfile::query()->create([
            'branch_id' => $this->branch->id,
            'accounting_enabled_modules' => ['core', 'credit_notes'],
        ]);

        $gate = new BranchAccountingModuleGate;

        $this->assertFalse($gate->isEnabled('credit_notes', $this->branch->id));

        BranchAccountingProfile::query()->where('branch_id', $this->branch->id)->update([
            'accounting_enabled_modules' => ['core', 'ar_ap', 'credit_notes'],
        ]);

        $freshGate = new BranchAccountingModuleGate;
        $this->assertTrue($freshGate->isEnabled('credit_notes', $this->branch->id));
    }

    public function test_head_office_null_branch_id_resolves_to_core_only(): void
    {
        $gate = new BranchAccountingModuleGate;

        $this->assertSame(['core'], $gate->enabledModules(null));
    }

    public function test_enabled_modules_returns_full_resolved_list(): void
    {
        BranchAccountingProfile::query()->create([
            'branch_id' => $this->branch->id,
            'accounting_enabled_modules' => ['core', 'cost_centres', 'multi_currency'],
        ]);

        $gate = new BranchAccountingModuleGate;
        $enabled = $gate->enabledModules($this->branch->id);

        $this->assertContains('core', $enabled);
        $this->assertContains('cost_centres', $enabled);
        $this->assertContains('multi_currency', $enabled);
        $this->assertNotContains('fixed_assets', $enabled);
        $this->assertNotContains('intercompany', $enabled);
    }
}
