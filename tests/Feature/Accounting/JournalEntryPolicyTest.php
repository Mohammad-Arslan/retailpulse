<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\JournalEntryStatus;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class JournalEntryPolicyTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    private function createJournalEntry(): JournalEntry
    {
        $account = ChartOfAccount::query()->create(['code' => '1000', 'name' => 'Cash', 'type' => 'asset']);

        return JournalEntry::query()->create([
            'journal_number' => 'JV-0001',
            'journal_date' => '2026-06-15',
            'status' => JournalEntryStatus::Draft,
        ]);
    }

    public function test_user_with_only_view_permission_cannot_post_a_journal(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('accounting.view');

        $entry = $this->createJournalEntry();

        $this->assertTrue($user->can('view', $entry));
        $this->assertFalse($user->can('post', $entry));
        $this->assertFalse($user->can('reverse', $entry));
        $this->assertFalse($user->can('create', JournalEntry::class));
    }

    public function test_user_with_post_permission_but_not_reverse_permission_cannot_reverse(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('accounting.post-journal');

        $entry = $this->createJournalEntry();

        $this->assertTrue($user->can('post', $entry));
        $this->assertFalse($user->can('reverse', $entry));
    }

    public function test_user_with_reverse_permission_can_reverse(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('accounting.reverse-journal');

        $entry = $this->createJournalEntry();

        $this->assertTrue($user->can('reverse', $entry));
    }

    public function test_user_with_create_permission_can_create_but_not_approve(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('accounting.create-journal');

        $entry = $this->createJournalEntry();

        $this->assertTrue($user->can('create', JournalEntry::class));
        $this->assertFalse($user->can('approve', $entry));
    }

    public function test_user_with_no_accounting_permissions_cannot_view_any_journals(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->assertFalse($user->can('viewAny', JournalEntry::class));
    }
}
