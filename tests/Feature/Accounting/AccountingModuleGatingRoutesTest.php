<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\BranchAccountingProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

/**
 * No admin route exists yet for the `intercompany` module (it has no dedicated controller
 * in this phase), so its dependency-on-multi_currency behaviour is covered at the unit
 * level in AccountingModuleGateTest instead of here.
 */
final class AccountingModuleGatingRoutesTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'Test Branch',
            'code' => 'TST',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');
    }

    private function setEnabledModules(array $modules): void
    {
        BranchAccountingProfile::query()->updateOrCreate(
            ['branch_id' => $this->branch->id],
            ['accounting_enabled_modules' => $modules],
        );
    }

    private function actingAsBranchAdmin(): TestResponse|static
    {
        return $this->actingAs($this->admin)->withSession(['branch_id' => $this->branch->id]);
    }

    public function test_core_only_branch_redirects_with_error_on_cost_centres_index(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.cost-centres.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_core_only_branch_redirects_with_error_on_bank_reconciliation_index(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.reconciliation.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_core_only_branch_redirects_with_error_on_bank_accounts_index(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.bank-accounts.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_core_only_branch_redirects_with_error_on_petty_cash_index(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.petty-cash.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_core_only_branch_redirects_with_error_on_cheques_index(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.cheques.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_core_only_branch_redirects_with_error_on_fixed_assets_index(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.fixed-assets.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_core_only_branch_redirects_with_error_on_credit_notes_index(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.credit-notes.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_core_only_branch_redirects_with_error_on_tax_types_index(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.tax-types.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_core_only_branch_redirects_with_error_on_currencies_index(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.currencies.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_core_only_branch_can_access_chart_of_accounts(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.chart-of-accounts.index'))
            ->assertOk();
    }

    public function test_core_only_branch_can_access_posting_rules(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.posting-rules.index'))
            ->assertOk();
    }

    public function test_core_only_branch_can_access_journal_entries(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.journal-entries.index'))
            ->assertOk();
    }

    public function test_core_only_branch_can_access_reports_index(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.reports.index'))
            ->assertOk();
    }

    public function test_enabling_multi_currency_without_explicitly_listing_core_still_works(): void
    {
        $this->setEnabledModules(['multi_currency']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.currencies.index'))
            ->assertOk();

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.chart-of-accounts.index'))
            ->assertOk();
    }

    public function test_enabling_credit_notes_alone_without_ar_ap_still_redirects_with_error(): void
    {
        $this->setEnabledModules(['core', 'credit_notes']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.credit-notes.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_enabling_credit_notes_with_ar_ap_allows_access(): void
    {
        $this->setEnabledModules(['core', 'ar_ap', 'credit_notes']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.credit-notes.index'))
            ->assertOk();
    }

    public function test_core_only_branch_redirects_with_error_on_debit_notes_index(): void
    {
        $this->setEnabledModules(['core']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.debit-notes.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_enabling_debit_notes_alone_without_ar_ap_still_redirects_with_error(): void
    {
        $this->setEnabledModules(['core', 'debit_notes']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.debit-notes.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_enabling_debit_notes_with_ar_ap_allows_access(): void
    {
        $this->setEnabledModules(['core', 'ar_ap', 'debit_notes']);

        $this->actingAsBranchAdmin()
            ->get(route('admin.accounting.debit-notes.index'))
            ->assertOk();
    }
}
