<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\OrganizationEntity;
use App\Models\User;
use App\Services\Payroll\EmployeeSelfServiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12EmployeeUserLinkTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private OrganizationEntity $entity;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'Link Test Branch',
            'code' => 'LNK1',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Link Test Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr'],
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');
    }

    private function actingAsBranchAdmin(): TestResponse|static
    {
        return $this->actingAs($this->admin)->withSession(['branch_id' => $this->branch->id]);
    }

    private function employeePayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'hire_date' => '2026-01-15',
            'employment_type' => 'full_time',
            'status' => 'active',
        ], $overrides);
    }

    public function test_employee_form_links_user_on_create(): void
    {
        $linkTarget = User::factory()->create(['is_active' => true]);

        $this->actingAsBranchAdmin()
            ->post(route('admin.hr.employees.store'), $this->employeePayload(['user_id' => $linkTarget->id]))
            ->assertRedirect();

        $employee = Employee::query()->where('first_name', 'Sara')->first();
        $this->assertNotNull($employee);
        $this->assertSame($linkTarget->id, $employee->user_id);
    }

    public function test_employee_form_links_and_unlinks_user_on_update(): void
    {
        $linkTarget = User::factory()->create(['is_active' => true]);

        $employee = Employee::query()->create([
            ...$this->employeePayload(),
            'employee_code' => 'EMP-LNK-1',
        ]);

        $this->actingAsBranchAdmin()
            ->put(route('admin.hr.employees.update', $employee), $this->employeePayload(['user_id' => $linkTarget->id]))
            ->assertRedirect();

        $this->assertSame($linkTarget->id, $employee->fresh()->user_id);

        $this->actingAsBranchAdmin()
            ->put(route('admin.hr.employees.update', $employee), $this->employeePayload(['user_id' => null]))
            ->assertRedirect();

        $this->assertNull($employee->fresh()->user_id);
    }

    public function test_employee_form_rejects_user_already_linked_to_another_employee(): void
    {
        $linkTarget = User::factory()->create(['is_active' => true]);

        Employee::query()->create([
            ...$this->employeePayload(['first_name' => 'Existing']),
            'employee_code' => 'EMP-LNK-2',
            'user_id' => $linkTarget->id,
        ]);

        $this->actingAsBranchAdmin()
            ->post(route('admin.hr.employees.store'), $this->employeePayload(['first_name' => 'NewGuy', 'user_id' => $linkTarget->id]))
            ->assertSessionHasErrors('user_id');

        $this->assertNull(Employee::query()->where('first_name', 'NewGuy')->first());
    }

    public function test_user_form_links_employee_on_create(): void
    {
        $employee = Employee::query()->create([
            ...$this->employeePayload(),
            'employee_code' => 'EMP-LNK-3',
        ]);

        $this->actingAsBranchAdmin()
            ->post(route('admin.users.store'), [
                'name' => 'New Linked User',
                'email' => 'linked-user@example.com',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'is_active' => true,
                'employee_id' => $employee->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'linked-user@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame($user->id, $employee->fresh()->user_id);
    }

    public function test_user_form_rejects_employee_already_linked_to_another_user(): void
    {
        $existingUser = User::factory()->create(['is_active' => true]);
        $employee = Employee::query()->create([
            ...$this->employeePayload(),
            'employee_code' => 'EMP-LNK-4',
            'user_id' => $existingUser->id,
        ]);

        $this->actingAsBranchAdmin()
            ->post(route('admin.users.store'), [
                'name' => 'Conflicting User',
                'email' => 'conflict@example.com',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'is_active' => true,
                'employee_id' => $employee->id,
            ])
            ->assertSessionHasErrors('employee_id');

        $this->assertNull(User::query()->where('email', 'conflict@example.com')->first());
        $this->assertSame($existingUser->id, $employee->fresh()->user_id);
    }

    public function test_user_form_relinks_and_unlinks_employee_on_update(): void
    {
        $employeeA = Employee::query()->create([
            ...$this->employeePayload(['first_name' => 'AliceA']),
            'employee_code' => 'EMP-LNK-5',
        ]);
        $employeeB = Employee::query()->create([
            ...$this->employeePayload(['first_name' => 'BobB']),
            'employee_code' => 'EMP-LNK-6',
        ]);

        $targetUser = User::factory()->create(['is_active' => true]);

        $this->actingAsBranchAdmin()
            ->put(route('admin.users.update', $targetUser), [
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'is_active' => true,
                'employee_id' => $employeeA->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame($targetUser->id, $employeeA->fresh()->user_id);

        // Switching the link to employee B must unlink employee A.
        $this->actingAsBranchAdmin()
            ->put(route('admin.users.update', $targetUser), [
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'is_active' => true,
                'employee_id' => $employeeB->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull($employeeA->fresh()->user_id);
        $this->assertSame($targetUser->id, $employeeB->fresh()->user_id);

        // Clearing the link entirely unlinks employee B without deleting either record.
        $this->actingAsBranchAdmin()
            ->put(route('admin.users.update', $targetUser), [
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'is_active' => true,
                'employee_id' => null,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull($employeeB->fresh()->user_id);
        $this->assertNotNull(Employee::query()->find($employeeA->id));
        $this->assertNotNull(Employee::query()->find($employeeB->id));
    }

    public function test_ess_resolves_employee_via_linked_user(): void
    {
        $employee = Employee::query()->create([
            ...$this->employeePayload(),
            'employee_code' => 'EMP-LNK-7',
        ]);
        $linkedUser = User::factory()->create(['is_active' => true]);
        $employee->update(['user_id' => $linkedUser->id]);

        $resolved = app(EmployeeSelfServiceService::class)->resolveEmployeeForUser($linkedUser);

        $this->assertSame($employee->id, $resolved->id);
    }
}
