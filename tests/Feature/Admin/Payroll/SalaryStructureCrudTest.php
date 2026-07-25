<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Payroll;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\OrganizationEntity;
use App\Models\PayComponent;
use App\Models\SalaryStructure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class SalaryStructureCrudTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private User $payrollOfficer;

    private PayComponent $basic;

    private PayComponent $allowance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        $this->payrollOfficer = User::factory()->create(['is_active' => true]);
        $this->payrollOfficer->assignRole('payroll-officer');

        $this->basic = PayComponent::query()->create([
            'code' => 'BASIC',
            'name' => 'Basic Pay',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'taxable' => true,
            'effective_from' => '2020-01-01',
            'status' => 'active',
        ]);

        $this->allowance = PayComponent::query()->create([
            'code' => 'HRALLOW',
            'name' => 'House Rent Allowance',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'taxable' => true,
            'effective_from' => '2020-01-01',
            'status' => 'active',
        ]);
    }

    public function test_user_without_permission_gets_redirected_with_error_on_index(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('cashier');

        $this->actingAs($user)
            ->get(route('admin.payroll.salary-structures.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_user_without_permission_cannot_create_a_structure(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('cashier');

        $this->actingAs($user)
            ->post(route('admin.payroll.salary-structures.store'), [
                'name' => 'Should Not Save',
                'status' => 'active',
                'components' => [],
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('salary_structures', ['name' => 'Should Not Save']);
    }

    public function test_payroll_officer_can_create_a_structure_with_components(): void
    {
        $this->actingAs($this->payrollOfficer)
            ->post(route('admin.payroll.salary-structures.store'), [
                'name' => 'Standard Structure',
                'status' => 'active',
                'components' => [
                    ['pay_component_id' => $this->basic->id, 'amount_or_rate' => 50000, 'sequence' => 10],
                    ['pay_component_id' => $this->allowance->id, 'amount_or_rate' => 15000, 'sequence' => 20],
                ],
            ])
            ->assertRedirect();

        $structure = SalaryStructure::query()->where('name', 'Standard Structure')->firstOrFail();

        $this->assertNotEmpty($structure->code);
        $this->assertSame('active', $structure->status);
        $this->assertCount(2, $structure->components);
        $this->assertDatabaseHas('salary_structure_components', [
            'salary_structure_id' => $structure->id,
            'pay_component_id' => $this->basic->id,
            'amount_or_rate' => 50000,
        ]);
        $this->assertDatabaseHas('salary_structure_components', [
            'salary_structure_id' => $structure->id,
            'pay_component_id' => $this->allowance->id,
            'amount_or_rate' => 15000,
        ]);
    }

    public function test_payroll_officer_can_update_a_structure_and_replace_its_components(): void
    {
        $structure = SalaryStructure::query()->create([
            'code' => 'SALSTR-0001',
            'name' => 'Original Structure',
            'status' => 'active',
        ]);
        $structure->components()->create([
            'pay_component_id' => $this->basic->id,
            'amount_or_rate' => 40000,
            'sequence' => 10,
        ]);

        $this->actingAs($this->payrollOfficer)
            ->put(route('admin.payroll.salary-structures.update', $structure), [
                'name' => 'Renamed Structure',
                'status' => 'active',
                'components' => [
                    ['pay_component_id' => $this->allowance->id, 'amount_or_rate' => 20000, 'sequence' => 10],
                ],
            ])
            ->assertRedirect();

        $structure->refresh();
        $this->assertSame('Renamed Structure', $structure->name);
        $this->assertCount(1, $structure->components);
        $this->assertSame($this->allowance->id, $structure->components->first()->pay_component_id);
        $this->assertDatabaseMissing('salary_structure_components', [
            'salary_structure_id' => $structure->id,
            'pay_component_id' => $this->basic->id,
        ]);
    }

    public function test_index_lists_created_structures(): void
    {
        SalaryStructure::query()->create([
            'code' => 'SALSTR-0002',
            'name' => 'Listed Structure',
            'status' => 'active',
        ]);

        $this->actingAs($this->payrollOfficer)
            ->get(route('admin.payroll.salary-structures.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Payroll/SalaryStructures/Index')
                ->where('structures.data.0.name', 'Listed Structure'));
    }

    public function test_employee_can_be_assigned_the_new_structure(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Salary Structure Test Branch',
            'code' => 'SST1',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $entity = OrganizationEntity::query()->create([
            'legal_name' => 'Salary Structure Test Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $branch->id,
            'hr_enabled_modules' => ['hr'],
        ]);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $structure = SalaryStructure::query()->create([
            'code' => 'SALSTR-0003',
            'name' => 'Assignable Structure',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'first_name' => 'Imran',
            'last_name' => 'Baig',
            'legal_entity_id' => $entity->id,
            'primary_branch_id' => $branch->id,
            'employee_code' => 'EMP-SAL-1',
            'hire_date' => '2026-01-15',
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->withSession(['branch_id' => $branch->id])
            ->get(route('admin.hr.employees.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('salaryStructures.0.id', $structure->id));

        $this->actingAs($admin)
            ->withSession(['branch_id' => $branch->id])
            ->put(route('admin.hr.employees.update', $employee), [
                'first_name' => 'Imran',
                'last_name' => 'Baig',
                'legal_entity_id' => $entity->id,
                'primary_branch_id' => $branch->id,
                'hire_date' => '2026-01-15',
                'employment_type' => 'full_time',
                'status' => 'active',
                'salary_structure_id' => $structure->id,
            ])
            ->assertRedirect();

        $this->assertSame($structure->id, $employee->fresh()->salary_structure_id);
    }
}
