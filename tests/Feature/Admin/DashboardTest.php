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

    public function test_super_admin_dashboard_includes_chart_data(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('stats', fn (Assert $stats) => $stats
                    ->has('users')
                    ->has('roles')
                    ->has('permissions')
                    ->has('active_users')
                    ->has('inactive_users')
                )
                ->has('charts', fn (Assert $charts) => $charts
                    ->has('user_growth', 7)
                    ->has('users_by_role')
                    ->has('permissions_by_group')
                    ->has('user_status', 2)
                )
            );
    }
}
