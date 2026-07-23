<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Http\Requests\Admin\Payroll\StorePayComponentRequest;
use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\OrganizationEntity;
use App\Models\PayComponent;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\StatutoryScheme;
use App\Models\TaxSlab;
use App\Models\User;
use App\Services\Payroll\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Block6PayrollCalculationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private User $admin;

    private OrganizationEntity $pkEntity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'Payroll Branch',
            'code' => 'PRBR',
            'currency' => 'PKR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->pkEntity = OrganizationEntity::query()->create([
            'legal_name' => 'PK Entity',
            'functional_currency_code' => 'PKR',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'attendance', 'leave', 'overtime', 'payroll'],
        ]);
    }

    /**
     * Test 1: PK-style income tax slabs + EOBI produce correct component codes and amounts.
     */
    public function test_pk_income_tax_slabs_and_eobi_produce_correct_payroll_lines(): void
    {
        // Seed pay components
        $basicComponent = $this->createPayComponent('BASIC', 'Basic Salary', 'earning', 'fixed', null, null, true, 'payroll_expense');
        $hraComponent = $this->createPayComponent('HRA', 'House Rent Allowance', 'earning', 'percentage_of', $basicComponent->id, 20, true, 'hra_expense');
        $incomeTaxComponent = $this->createPayComponent('INCOME_TAX', 'Income Tax', 'deduction', 'table_lookup', null, null, false, 'tax_withheld_payable');

        // Salary structure: BASIC(10) → HRA(20) → INCOME_TAX(30)
        $structure = $this->createSalaryStructure('PK-STANDARD', 'PK Standard', $this->pkEntity->id);
        $this->attachComponent($structure, $basicComponent, '50000', 10);
        $this->attachComponent($structure, $hraComponent, null, 20); // uses component rate
        $this->attachComponent($structure, $incomeTaxComponent, null, 30);

        // PK tax slabs (2024-25 simplified)
        // Slab 1: 0 – 600000/year: 0%
        TaxSlab::query()->create([
            'legal_entity_id' => $this->pkEntity->id,
            'effective_from' => '2024-01-01',
            'lower_bound' => 0,
            'upper_bound' => 600000,
            'fixed_amount' => 0,
            'marginal_rate' => 0,
            'status' => 'active',
        ]);
        // Slab 2: 600001 – 1200000/year: 2.5% of excess
        TaxSlab::query()->create([
            'legal_entity_id' => $this->pkEntity->id,
            'effective_from' => '2024-01-01',
            'lower_bound' => 600000,
            'upper_bound' => 1200000,
            'fixed_amount' => 0,
            'marginal_rate' => 2.5,
            'status' => 'active',
        ]);
        // Slab 3: 1200001+/year: 15000 + 12.5% of excess
        TaxSlab::query()->create([
            'legal_entity_id' => $this->pkEntity->id,
            'effective_from' => '2024-01-01',
            'lower_bound' => 1200000,
            'upper_bound' => null,
            'fixed_amount' => 15000,
            'marginal_rate' => 12.5,
            'status' => 'active',
        ]);

        // EOBI statutory scheme: 1% employee, 5% employer, no wage ceiling
        StatutoryScheme::query()->create([
            'code' => 'EOBI-PK',
            'name' => 'Employees Old-Age Benefits Institution',
            'legal_entity_id' => $this->pkEntity->id,
            'calculation_type' => 'percentage_of_wage',
            'employee_rate' => 1,
            'employer_rate' => 5,
            'wage_ceiling' => null,
            'account_mapping_key_employee' => 'statutory_payable',
            'account_mapping_key_employer' => 'employer_contribution_expense',
            'effective_from' => '2024-01-01',
            'status' => 'active',
        ]);

        // Create employee with PK entity and salary structure
        $employee = $this->createEmployee($this->pkEntity->id, $this->branch->id, $structure->id);

        // Create draft payroll run
        $run = PayrollRun::query()->create([
            'legal_entity_id' => $this->pkEntity->id,
            'branch_id' => $this->branch->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'currency_code' => 'PKR',
            'status' => 'draft',
        ]);

        /** @var PayrollCalculationService $service */
        $service = app(PayrollCalculationService::class);
        $processedRun = $service->processRun($run);

        // Calculation leaves the run in draft (posting is Block 7).
        $this->assertSame('draft', $processedRun->status);
        $this->assertNotNull($processedRun->totals_json['calculated_at'] ?? null);

        /** @var PayrollItem $item */
        $item = PayrollItem::query()
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->with('lines')
            ->firstOrFail();

        $linesByCode = $item->lines->keyBy(fn ($line) => $line->component_snapshot_json['code'] ?? $line->component_snapshot_json['scheme_code'] ?? null);

        // BASIC = 50000
        $this->assertNotNull($linesByCode->get('BASIC'), 'BASIC line must exist');
        $this->assertSame(0, bccomp('50000.0000', (string) $linesByCode->get('BASIC')->amount, 4), 'BASIC must be 50000');

        // HRA = 20% of BASIC = 10000
        $this->assertNotNull($linesByCode->get('HRA'), 'HRA line must exist');
        $this->assertSame(0, bccomp('10000.0000', (string) $linesByCode->get('HRA')->amount, 4), 'HRA must be 10000 (20% of 50000)');

        // Income tax: (50000+10000)*12 = 720000/year → Slab 2: 0 + 2.5%*(720000-600000) = 3000/year → 250/month
        $this->assertNotNull($linesByCode->get('INCOME_TAX'), 'INCOME_TAX line must exist');
        $this->assertSame(0, bccomp('250.0000', (string) $linesByCode->get('INCOME_TAX')->amount, 4), 'Income tax must be 250');

        // EOBI employee: 1% of gross 60000 = 600
        $eobiEmployeeLine = $item->lines->first(fn ($l) => ($l->component_snapshot_json['scheme_code'] ?? null) === 'EOBI-PK'
            && ($l->component_snapshot_json['side'] ?? null) === 'employee');
        $this->assertNotNull($eobiEmployeeLine, 'EOBI employee line must exist');
        $this->assertSame(0, bccomp('600.0000', (string) $eobiEmployeeLine->amount, 4), 'EOBI employee must be 600');
        $this->assertSame('statutory_payable', $eobiEmployeeLine->component_snapshot_json['account_mapping_key']);

        // EOBI employer: 5% of 60000 = 3000
        $eobiEmployerLine = $item->lines->first(fn ($l) => ($l->component_snapshot_json['scheme_code'] ?? null) === 'EOBI-PK'
            && ($l->component_snapshot_json['side'] ?? null) === 'employer');
        $this->assertNotNull($eobiEmployerLine, 'EOBI employer line must exist');
        $this->assertSame(0, bccomp('3000.0000', (string) $eobiEmployerLine->amount, 4), 'EOBI employer must be 3000');
        $this->assertSame('employer_contribution_expense', $eobiEmployerLine->component_snapshot_json['account_mapping_key']);

        // Totals
        $this->assertSame(0, bccomp('60000.0000', (string) $item->gross, 4), 'Gross must be 60000');
        $this->assertSame(0, bccomp('850.0000', (string) $item->total_deductions, 4), 'Total deductions must be 850 (250 tax + 600 EOBI)');
        $this->assertSame(0, bccomp('59150.0000', (string) $item->net_pay, 4), 'Net pay must be 59150');
    }

    /**
     * Test 2: UAE GPSSA scheme is config-only — generic handler produces lines with correct mapping keys.
     * No new PHP calculation branch for GPSSA vs EOBI.
     */
    public function test_uae_gpssa_scheme_uses_generic_statutory_handler(): void
    {
        $uaeEntity = OrganizationEntity::query()->create([
            'legal_name' => 'UAE Entity',
            'functional_currency_code' => 'AED',
            'status' => 'active',
        ]);

        // Same pay components (fixed BASIC only, no tax slabs for UAE in this test)
        $basicComponent = $this->createPayComponent('BASIC_UAE', 'Basic Salary UAE', 'earning', 'fixed', null, null, true, 'payroll_expense');
        $structure = $this->createSalaryStructure('UAE-STANDARD', 'UAE Standard', $uaeEntity->id);
        $this->attachComponent($structure, $basicComponent, '20000', 10);

        // GPSSA: 5% employee, 12.5% employer (UAE private sector)
        StatutoryScheme::query()->create([
            'code' => 'GPSSA',
            'name' => 'General Pension and Social Security Authority',
            'legal_entity_id' => $uaeEntity->id,
            'calculation_type' => 'percentage_of_wage',
            'employee_rate' => 5,
            'employer_rate' => 12.5,
            'wage_ceiling' => null,
            'account_mapping_key_employee' => 'statutory_payable',
            'account_mapping_key_employer' => 'employer_contribution_expense',
            'effective_from' => '2024-01-01',
            'status' => 'active',
        ]);

        $employee = $this->createEmployee($uaeEntity->id, $this->branch->id, $structure->id);

        $run = PayrollRun::query()->create([
            'legal_entity_id' => $uaeEntity->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'currency_code' => 'AED',
            'status' => 'draft',
        ]);

        /** @var PayrollCalculationService $service */
        $service = app(PayrollCalculationService::class);
        $service->processRun($run);

        /** @var PayrollItem $item */
        $item = PayrollItem::query()
            ->where('payroll_run_id', $run->id)
            ->with('lines')
            ->firstOrFail();

        // Assert GPSSA lines exist via generic handler
        $gpssaEmployeeLine = $item->lines->first(fn ($l) => ($l->component_snapshot_json['scheme_code'] ?? null) === 'GPSSA'
            && ($l->component_snapshot_json['side'] ?? null) === 'employee');
        $this->assertNotNull($gpssaEmployeeLine, 'GPSSA employee line must exist via generic handler');
        $this->assertSame('statutory_payable', $gpssaEmployeeLine->component_snapshot_json['account_mapping_key']);

        // Employee GPSSA: 5% of 20000 = 1000
        $this->assertSame(0, bccomp('1000.0000', (string) $gpssaEmployeeLine->amount, 4), 'GPSSA employee must be 1000');

        $gpssaEmployerLine = $item->lines->first(fn ($l) => ($l->component_snapshot_json['scheme_code'] ?? null) === 'GPSSA'
            && ($l->component_snapshot_json['side'] ?? null) === 'employer');
        $this->assertNotNull($gpssaEmployerLine, 'GPSSA employer line must exist via generic handler');
        $this->assertSame('employer_contribution_expense', $gpssaEmployerLine->component_snapshot_json['account_mapping_key']);

        // Employer GPSSA: 12.5% of 20000 = 2500
        $this->assertSame(0, bccomp('2500.0000', (string) $gpssaEmployerLine->amount, 4), 'GPSSA employer must be 2500');

        // Confirm there is no hardcoded 'GPSSA' or 'EOBI' branch in the engine source
        $enginePath = app_path('Services/Payroll/PayrollCalculationService.php');
        $this->assertStringNotContainsString("'GPSSA'", file_get_contents($enginePath), 'Engine must not hardcode GPSSA scheme code');
        $this->assertStringNotContainsString("'EOBI'", file_get_contents($enginePath), 'Engine must not hardcode EOBI scheme code');
    }

    /**
     * Test 3: Changing component config after a run is processed does NOT alter the frozen snapshot.
     */
    public function test_snapshot_is_immutable_after_processing(): void
    {
        $basicComponent = $this->createPayComponent('BASIC_SNAP', 'Basic Salary Snap', 'earning', 'fixed', null, null, true, 'payroll_expense');
        $structure = $this->createSalaryStructure('SNAP-TEST', 'Snapshot Test', $this->pkEntity->id);
        $this->attachComponent($structure, $basicComponent, '80000', 10);

        $employee = $this->createEmployee($this->pkEntity->id, $this->branch->id, $structure->id);

        $run = PayrollRun::query()->create([
            'legal_entity_id' => $this->pkEntity->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'currency_code' => 'PKR',
            'status' => 'draft',
        ]);

        /** @var PayrollCalculationService $service */
        $service = app(PayrollCalculationService::class);
        $service->processRun($run);

        /** @var PayrollItem $item */
        $item = PayrollItem::query()
            ->where('payroll_run_id', $run->id)
            ->with('lines')
            ->firstOrFail();

        // Verify original snapshot amount
        $snapshot = $item->snapshot_json;
        $this->assertSame('80000.0000', $snapshot['component_amounts']['BASIC_SNAP'] ?? null);

        $basicLine = $item->lines->first(fn ($l) => ($l->component_snapshot_json['code'] ?? null) === 'BASIC_SNAP');
        $this->assertSame(0, bccomp('80000.0000', (string) $basicLine->amount, 4));

        // Now change the structure component amount
        SalaryStructureComponent::query()
            ->where('salary_structure_id', $structure->id)
            ->where('pay_component_id', $basicComponent->id)
            ->update(['amount_or_rate' => 999999]);

        // Re-fetch original item from DB
        $originalItem = PayrollItem::query()->find($item->id);
        $this->assertNotNull($originalItem);

        // Snapshot must still show the original 80000
        $this->assertSame('80000.0000', $originalItem->snapshot_json['component_amounts']['BASIC_SNAP'] ?? null);

        // Original line amount must be unchanged
        $originalLine = $item->lines->first(fn ($l) => ($l->component_snapshot_json['code'] ?? null) === 'BASIC_SNAP');
        $this->assertNotNull($originalLine);
        $originalLine->refresh();
        $this->assertSame(0, bccomp('80000.0000', (string) $originalLine->amount, 4), 'Stored line amount must be unchanged');
    }

    /**
     * Test 4: Storing a pay component with calculation_type=formula fails validation.
     */
    public function test_formula_calculation_type_fails_validation(): void
    {
        $request = new StorePayComponentRequest;
        $validator = Validator::make(
            [
                'code' => 'FORMULA_TEST',
                'name' => 'Formula Test',
                'type' => 'earning',
                'calculation_type' => 'formula',
                'taxable' => false,
                'effective_from' => '2026-01-01',
                'status' => 'active',
            ],
            $request->rules(),
            $request->messages(),
        );

        $this->assertTrue($validator->fails(), 'Validator must reject formula calculation_type');
        $this->assertArrayHasKey('calculation_type', $validator->errors()->toArray());
        $this->assertStringContainsString(
            'Formula Components Are Not Supported Yet',
            $validator->errors()->first('calculation_type'),
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createPayComponent(
        string $code,
        string $name,
        string $type,
        string $calculationType,
        ?int $basisComponentId,
        ?float $rate,
        bool $taxable,
        string $accountMappingKey,
    ): PayComponent {
        return PayComponent::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'calculation_type' => $calculationType,
            'basis_component_id' => $basisComponentId,
            'rate' => $rate,
            'taxable' => $taxable,
            'account_mapping_key' => $accountMappingKey,
            'effective_from' => '2024-01-01',
            'status' => 'active',
        ]);
    }

    private function createSalaryStructure(string $code, string $name, int $legalEntityId): SalaryStructure
    {
        return SalaryStructure::query()->create([
            'code' => $code,
            'name' => $name,
            'legal_entity_id' => $legalEntityId,
            'status' => 'active',
        ]);
    }

    private function attachComponent(
        SalaryStructure $structure,
        PayComponent $component,
        ?string $amountOrRate,
        int $sequence,
    ): SalaryStructureComponent {
        return SalaryStructureComponent::query()->create([
            'salary_structure_id' => $structure->id,
            'pay_component_id' => $component->id,
            'amount_or_rate' => $amountOrRate,
            'sequence' => $sequence,
        ]);
    }

    private function createEmployee(int $legalEntityId, int $branchId, int $salaryStructureId): Employee
    {
        static $counter = 0;
        $counter++;

        return Employee::query()->create([
            'employee_code' => 'EMP'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'legal_entity_id' => $legalEntityId,
            'primary_branch_id' => $branchId,
            'salary_structure_id' => $salaryStructureId,
            'hire_date' => '2024-01-01',
            'status' => 'active',
            'first_name' => 'Test',
            'last_name' => 'Employee '.$counter,
        ]);
    }
}
