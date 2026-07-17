<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ProductType;
use App\Enums\WarehouseType;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class WarehouseCrudTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

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

        Warehouse::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Main',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_user_without_view_permission_gets_redirected_with_error_on_index(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('cashier');

        $this->actingAs($user)
            ->get(route('admin.warehouses.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_branch_manager_can_create_second_warehouse(): void
    {
        $manager = User::factory()->create(['is_active' => true]);
        $manager->assignRole('branch-manager');
        $manager->branches()->attach($this->branch->id, ['is_primary' => true]);

        $this->actingAs($manager)
            ->post(route('admin.warehouses.store'), [
                'branch_id' => $this->branch->id,
                'name' => 'Overflow',
                'type' => 'sales_floor',
                'is_default' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('warehouses', [
            'branch_id' => $this->branch->id,
            'name' => 'Overflow',
            'code' => 'OVERFLOW',
            'type' => 'sales_floor',
            'is_active' => true,
        ]);
    }

    public function test_auto_generated_codes_are_unique_per_branch(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        Warehouse::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Overflow Storage',
            'code' => 'OVERFLOW-STORAGE',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.warehouses.store'), [
                'branch_id' => $this->branch->id,
                'name' => 'Overflow Storage',
                'type' => 'backroom',
                'is_default' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('warehouses', [
            'branch_id' => $this->branch->id,
            'name' => 'Overflow Storage',
            'code' => 'OVERFLOW-STORAGE-2',
        ]);
    }

    public function test_deactivate_blocked_when_warehouse_has_on_hand_stock(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $second = Warehouse::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Secondary',
            'code' => 'SEC',
            'is_default' => false,
            'is_active' => true,
        ]);

        $unit = Unit::query()->create([
            'name' => 'Piece',
            'abbreviation' => 'pc',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-TEST',
            'sell_price' => 100,
            'is_default' => true,
        ]);

        Inventory::query()->create([
            'warehouse_id' => $second->id,
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.warehouses.edit', $second))
            ->patch(route('admin.warehouses.deactivate', $second))
            ->assertRedirect(route('admin.warehouses.edit', $second))
            ->assertSessionHasErrors('warehouse');

        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_deactivate_succeeds_when_warehouse_is_empty(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $second = Warehouse::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Secondary',
            'code' => 'SEC',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.warehouses.deactivate', $second))
            ->assertRedirect(route('admin.warehouses.index'));

        $this->assertFalse($second->fresh()->is_active);
    }

    public function test_setting_default_clears_siblings_for_branch(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $main = Warehouse::query()->where('branch_id', $this->branch->id)->where('code', 'MAIN')->firstOrFail();

        $second = Warehouse::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Secondary',
            'code' => 'SEC',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.warehouses.update', $second), [
                'name' => 'Secondary',
                'type' => 'offsite',
                'is_default' => true,
            ])
            ->assertRedirect(route('admin.warehouses.edit', $second));

        $this->assertFalse($main->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame('offsite', $second->fresh()->type?->value);
    }

    public function test_warehouses_of_type_helper_scopes_by_branch(): void
    {
        Warehouse::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Floor Stock',
            'code' => 'FLR',
            'type' => 'sales_floor',
            'is_default' => false,
            'is_active' => true,
        ]);

        $salesFloor = $this->branch->warehousesOfType(WarehouseType::SalesFloor)->get();

        $this->assertCount(1, $salesFloor);
        $this->assertSame('FLR', $salesFloor->first()?->code);
    }

    public function test_branch_manager_sees_only_assigned_branch_warehouses(): void
    {
        $otherBranch = Branch::query()->create([
            'name' => 'Other Branch',
            'code' => 'OTH',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        Warehouse::query()->create([
            'branch_id' => $otherBranch->id,
            'name' => 'Other Main',
            'code' => 'OTH-MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $manager = User::factory()->create(['is_active' => true]);
        $manager->assignRole('branch-manager');
        $manager->branches()->attach($this->branch->id, ['is_primary' => true]);

        $this->actingAs($manager)
            ->get(route('admin.warehouses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Warehouses/Index')
                ->has('warehouses.data', 1)
                ->where('warehouses.data.0.branch_id', $this->branch->id)
            );
    }

    public function test_super_admin_sees_warehouses_across_branches(): void
    {
        $otherBranch = Branch::query()->create([
            'name' => 'Other Branch',
            'code' => 'OTH',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        Warehouse::query()->create([
            'branch_id' => $otherBranch->id,
            'name' => 'Other Main',
            'code' => 'OTH-MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get(route('admin.warehouses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Warehouses/Index')
                ->has('warehouses.data', 2)
            );
    }
}
