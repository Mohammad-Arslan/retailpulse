<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\AccountingEvent;
use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\OrganizationEntity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingEventService;
use App\Services\Hr\BranchHrPayrollModuleGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Block0FoundationsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private User $admin;

    private OrganizationEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'HR Test Branch',
            'code' => 'HR1',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Test Legal Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');
    }

    private function actingAsBranchAdmin(): TestResponse|static
    {
        return $this->actingAs($this->admin)->withSession(['branch_id' => $this->branch->id]);
    }

    public function test_hr_module_gate_expands_attendance_dependency_on_hr(): void
    {
        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['attendance'],
        ]);

        $this->assertFalse((new BranchHrPayrollModuleGate)->isEnabled('attendance', $this->branch->id));

        BranchHrProfile::query()->where('branch_id', $this->branch->id)->update([
            'hr_enabled_modules' => ['hr', 'attendance'],
        ]);

        $this->assertTrue((new BranchHrPayrollModuleGate)->isEnabled('attendance', $this->branch->id));
        $this->assertTrue((new BranchHrPayrollModuleGate)->isEnabled('hr', $this->branch->id));
    }

    public function test_overtime_requires_hr_and_attendance(): void
    {
        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'overtime'],
        ]);

        $this->assertFalse((new BranchHrPayrollModuleGate)->isEnabled('overtime', $this->branch->id));

        BranchHrProfile::query()->where('branch_id', $this->branch->id)->update([
            'hr_enabled_modules' => ['hr', 'attendance', 'overtime'],
        ]);

        $this->assertTrue((new BranchHrPayrollModuleGate)->isEnabled('overtime', $this->branch->id));
    }

    public function test_hr_disabled_returns_403_on_employees_index(): void
    {
        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['expenses'],
        ]);

        $this->actingAsBranchAdmin()
            ->get(route('admin.hr.employees.index'))
            ->assertForbidden();
    }

    public function test_phase12_permissions_and_roles_are_seeded(): void
    {
        $this->assertTrue(Permission::query()->where('name', 'hr.manage-employees')->exists());
        $this->assertTrue(Permission::query()->where('name', 'payroll.post')->exists());
        $this->assertTrue(Permission::query()->where('name', 'expenses.approve')->exists());
        $this->assertTrue(Role::query()->where('name', 'hr-manager')->exists());
        $this->assertTrue(Role::query()->where('name', 'payroll-officer')->exists());
        $this->assertTrue(Role::query()->where('name', 'line-manager')->exists());
        $this->assertTrue(Role::query()->where('name', 'employee')->exists());
    }

    public function test_employee_crud_happy_path(): void
    {
        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'expenses'],
        ]);

        $this->actingAsBranchAdmin()
            ->post(route('admin.hr.employees.store'), [
                'first_name' => 'Ayesha',
                'last_name' => 'Khan',
                'email' => 'ayesha@example.com',
                'legal_entity_id' => $this->entity->id,
                'primary_branch_id' => $this->branch->id,
                'hire_date' => '2026-01-15',
                'employment_type' => 'full_time',
                'status' => 'active',
            ])
            ->assertRedirect();

        $employee = Employee::query()->first();
        $this->assertNotNull($employee);
        $this->assertNotEmpty($employee->employee_code);
        $this->assertSame('Ayesha', $employee->first_name);

        $this->actingAsBranchAdmin()
            ->get(route('admin.hr.employees.index'))
            ->assertOk();

        $this->actingAsBranchAdmin()
            ->put(route('admin.hr.employees.update', $employee), [
                'first_name' => 'Ayesha',
                'last_name' => 'Khan',
                'email' => 'ayesha@example.com',
                'legal_entity_id' => $this->entity->id,
                'primary_branch_id' => $this->branch->id,
                'hire_date' => '2026-01-15',
                'employment_type' => 'full_time',
                'status' => 'inactive',
            ])
            ->assertRedirect();

        $this->assertSame('inactive', $employee->fresh()->status);
    }

    public function test_find_for_source_round_trips_after_process(): void
    {
        $events = app(AccountingEventService::class);

        $created = $events->process(
            'phase12.probe',
            Employee::class,
            99,
            [
                'date' => '2026-07-15',
                'branch_id' => $this->branch->id,
                'legal_entity_id' => $this->entity->id,
                'currency_code' => 'USD',
            ],
            (int) $this->admin->id,
        );

        $this->assertSame(
            AccountingEvent::query()->where('idempotency_key', $created->idempotency_key)->first()?->id,
            $events->findForSource('phase12.probe', Employee::class, 99)?->id,
        );

        $this->assertSame(
            'phase12.probe:'.Employee::class.':99',
            $events->idempotencyKey('phase12.probe', Employee::class, 99),
        );
    }
}
