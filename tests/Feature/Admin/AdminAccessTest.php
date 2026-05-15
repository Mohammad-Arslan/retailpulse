<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class AdminAccessTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_guest_cannot_access_admin_routes(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_cashier_cannot_access_user_create(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('cashier');

        $this->actingAs($user)
            ->get(route('admin.users.create'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_user_with_role(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New User',
                'email' => 'newuser@test.local',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'is_active' => true,
                'roles' => ['owner'],
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'newuser@test.local']);
        $this->assertTrue(
            User::query()->where('email', 'newuser@test.local')->first()?->hasRole('owner') ?? false
        );
    }
}
