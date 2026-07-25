<?php

declare(strict_types=1);

namespace Tests\Feature\HelpSupport;

use App\Ai\Agents\GuideAssistantAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class BranchSetupGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_setup_guide_page_renders_for_an_authed_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(route('help-support.guides.branch-setup'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('HelpSupport/Guides/BranchSetup'));
    }

    public function test_branch_setup_guide_ask_resolves_without_unknown_guide_exception(): void
    {
        GuideAssistantAgent::fake();

        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->post(
            route('help-support.guides.ask', ['guide' => 'branch-setup']),
            ['message' => 'What order do I set up a branch in?'],
        );

        $response->assertOk();
        $response->assertDontSee('This guide does not exist.');
    }
}
