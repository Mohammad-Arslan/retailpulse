<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_super_admin_dashboard_includes_permission_driven_widgets(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('widgets')
                ->where('widgets', function ($widgets): bool {
                    $ids = collect($widgets)->pluck('id')->all();

                    return in_array('sales_kpis', $ids, true)
                        && in_array('business_exceptions', $ids, true)
                        && ! in_array('rbac', $ids, true);
                })
            );
    }
}
