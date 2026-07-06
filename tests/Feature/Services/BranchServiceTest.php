<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DTOs\Branch\CreateBranchData;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Services\BranchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class BranchServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_create_creates_branch_and_default_warehouse_in_one_transaction(): void
    {
        $service = app(BranchService::class);

        $branch = $service->create(new CreateBranchData(
            name: 'North Store',
            address: '123 Main St',
            currency: 'USD',
            timezone: 'UTC',
            operatingHours: [],
            receiptFooter: null,
            isActive: true,
            initialWarehouseId: null,
        ));

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'North Store',
        ]);

        $this->assertDatabaseHas('warehouses', [
            'branch_id' => $branch->id,
            'name' => 'Main Warehouse',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertCount(1, $branch->warehouses);
    }

    public function test_create_rolls_back_branch_when_initial_warehouse_template_is_invalid(): void
    {
        $service = app(BranchService::class);
        $branchCountBefore = Branch::query()->count();
        $warehouseCountBefore = Warehouse::query()->count();

        try {
            $service->create(new CreateBranchData(
                name: 'Failed Branch',
                address: null,
                currency: 'USD',
                timezone: 'UTC',
                operatingHours: [],
                receiptFooter: null,
                isActive: true,
                initialWarehouseId: 99999,
            ));
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame($branchCountBefore, Branch::query()->count());
        $this->assertSame($warehouseCountBefore, Warehouse::query()->count());
    }
}
