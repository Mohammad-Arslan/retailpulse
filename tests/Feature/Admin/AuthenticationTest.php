<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_root_redirects_guest_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_super_admin_can_login_and_reach_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole('super-admin');

        $response = $this->post('/login', [
            'email' => 'admin@test.local',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_user_without_admin_access_cannot_login_to_panel(): void
    {
        $user = User::factory()->create([
            'email' => 'cashier@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole('cashier');

        $this->post('/login', [
            'email' => 'cashier@test.local',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_account_locks_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'locked@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole('super-admin');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'locked@test.local',
                'password' => 'wrong-password',
            ]);
        }

        $user->refresh();
        $this->assertTrue($user->isLocked());

        $this->post('/login', [
            'email' => 'locked@test.local',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }
}
