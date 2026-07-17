<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\OrganizationEntity;
use App\Services\ImportExport\Handlers\AttendanceImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportExportRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2AttendanceHistoricalImportTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private OrganizationEntity $entity;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'Historical Attendance Branch',
            'code' => 'HABR',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Historical Attendance Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'attendance'],
        ]);

        $this->employee = Employee::query()->create([
            'employee_code' => 'EMP-HIST-'.uniqid(),
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'hire_date' => '2025-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Bilal',
            'last_name' => 'Chaudhry',
            'email' => 'bilal-'.uniqid().'@example.com',
        ]);
    }

    public function test_attendance_is_registered_in_the_import_export_registry(): void
    {
        $this->assertContains('attendance', ImportExportRegistry::allEntities());
        $this->assertInstanceOf(AttendanceImportHandler::class, ImportExportRegistry::importHandler('attendance'));
    }

    public function test_processing_a_row_creates_a_historical_attendance_record(): void
    {
        $handler = new AttendanceImportHandler;
        $context = $this->makeContext();

        $result = $handler->processRow([
            'employee_code' => $this->employee->employee_code,
            'branch_code' => $this->branch->code,
            'clock_in' => '2025-06-02 09:00:00',
            'clock_out' => '2025-06-02 17:30:00',
            'worked_minutes' => null,
        ], $context);

        $this->assertTrue($result->success);

        $record = AttendanceRecord::query()->findOrFail($result->recordId);
        $this->assertSame($this->employee->id, $record->employee_id);
        $this->assertSame($this->branch->id, $record->branch_id);
        $this->assertTrue($record->is_historical);
        $this->assertSame('closed', $record->status);
        $this->assertSame(510, $record->worked_minutes); // 8.5 hours
    }

    public function test_missing_employee_code_fails_cleanly_without_creating_a_record(): void
    {
        $handler = new AttendanceImportHandler;
        $context = $this->makeContext();

        $result = $handler->processRow([
            'employee_code' => 'DOES-NOT-EXIST',
            'branch_code' => null,
            'clock_in' => '2025-06-02 09:00:00',
            'clock_out' => null,
            'worked_minutes' => null,
        ], $context);

        $this->assertFalse($result->success);
        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_reimporting_the_same_row_is_idempotent(): void
    {
        $handler = new AttendanceImportHandler;
        $context = $this->makeContext();

        $row = [
            'employee_code' => $this->employee->employee_code,
            'branch_code' => $this->branch->code,
            'clock_in' => '2025-06-03 09:00:00',
            'clock_out' => '2025-06-03 17:00:00',
            'worked_minutes' => null,
        ];

        $first = $handler->processRow($row, $context);
        $second = $handler->processRow($row, $context);

        $this->assertSame($first->recordId, $second->recordId);
        $this->assertSame(1, AttendanceRecord::query()->count());
    }

    private function makeContext(): ImportContext
    {
        return new ImportContext(
            jobId: 1,
            tenantId: null,
            userId: 1,
            mode: 'create',
            isDryRun: false,
            filePath: 'dummy.csv',
            disk: 'local',
            options: [],
        );
    }
}
