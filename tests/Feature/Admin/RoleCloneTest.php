<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class RoleCloneTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_role_clone_copies_permissions(): void
    {
        $source = Role::findByName('owner');
        $cloned = app(RoleService::class)->clone($source, 'owner-copy');

        $this->assertEquals(
            $source->permissions->pluck('name')->sort()->values()->all(),
            $cloned->permissions->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_super_admin_can_clone_role_via_http(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $source = Role::findByName('owner');

        $this->actingAs($admin)
            ->post(route('admin.roles.clone.store', $source), [
                'name' => 'owner-cloned',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['name' => 'owner-cloned']);
    }
}
