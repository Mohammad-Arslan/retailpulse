<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class RoleServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_clone_creates_role_with_same_permissions(): void
    {
        $source = Role::findByName('branch-manager');

        $cloned = app(RoleService::class)->clone($source, 'branch-manager-copy');

        $this->assertSame('branch-manager-copy', $cloned->name);
        $this->assertFalse($cloned->is_system);
        $this->assertCount($source->permissions->count(), $cloned->permissions);
    }
}
