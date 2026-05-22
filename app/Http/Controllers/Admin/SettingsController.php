<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Settings\UpdateSettingsGroupData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsGroupRequest;
use App\Policies\SettingsPolicy;
use App\Services\SystemSettingService;
use App\Support\Settings\SettingGroupRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettingService $settings,
        private readonly SettingsPolicy $policy,
    ) {}

    public function index(Request $request): Response
    {
        if (! $this->policy->viewAny($request->user())) {
            abort(403);
        }

        return Inertia::render('Admin/Settings/Index', [
            'groups' => $this->settings->accessibleGroups($request->user()),
        ]);
    }

    public function edit(Request $request, string $group): Response
    {
        if (! SettingGroupRegistry::exists($group)) {
            abort(404);
        }

        if (! $this->policy->viewGroup($request->user(), $group)) {
            abort(403);
        }

        $this->settings->ensureGroupDefaults($group);

        return Inertia::render('Admin/Settings/Edit', $this->settings->groupForDisplay($group, $request->user()));
    }

    public function update(UpdateSettingsGroupRequest $request, string $group): RedirectResponse
    {
        if (! SettingGroupRegistry::exists($group)) {
            abort(404);
        }

        if (! $this->policy->updateGroup($request->user(), $group)) {
            abort(403);
        }

        $this->settings->updateGroup(
            UpdateSettingsGroupData::fromRequest($request, $group),
            $request->user(),
        );

        return redirect()
            ->route('admin.settings.edit', $group)
            ->with('success', __('Settings saved successfully.'));
    }
}
