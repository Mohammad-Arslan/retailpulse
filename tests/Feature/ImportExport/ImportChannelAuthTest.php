<?php

declare(strict_types=1);

namespace Tests\Feature\ImportExport;

use App\Models\ImportExportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ImportChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_channel_auth_requires_owner_user_id(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create(['tenant_id' => $owner->tenant_id]);

        $job = ImportExportJob::factory()->create([
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'type' => 'import',
            'entity_type' => 'brands',
        ]);

        // Owner can subscribe
        $response = $this->actingAs($owner)->postJson('/broadcasting/auth', [
            'channel_name' => "private-import-job.{$job->ulid}",
        ]);
        $response->assertOk();

        // Same-tenant non-owner cannot subscribe
        $response = $this->actingAs($other)->postJson('/broadcasting/auth', [
            'channel_name' => "private-import-job.{$job->ulid}",
        ]);
        $response->assertForbidden();
    }

    public function test_channel_auth_rejects_different_tenant(): void
    {
        $owner = User::factory()->create();
        $otherTenant = User::factory()->create(['tenant_id' => $owner->tenant_id + 999]);

        $job = ImportExportJob::factory()->create([
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'type' => 'import',
            'entity_type' => 'brands',
        ]);

        $response = $this->actingAs($otherTenant)->postJson('/broadcasting/auth', [
            'channel_name' => "private-import-job.{$job->ulid}",
        ]);
        $response->assertForbidden();
    }
}
