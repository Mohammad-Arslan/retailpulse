<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hr\UpdateHrEntitySettingsRequest;
use App\Models\HrEntitySetting;
use App\Services\Hr\HrEntitySettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class HrEntitySettingsController extends Controller
{
    public function __construct(
        private readonly HrEntitySettingsService $settings,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', HrEntitySetting::class);

        return Inertia::render('Admin/Hr/Settings/Index', $this->settings->indexPayload());
    }

    public function update(UpdateHrEntitySettingsRequest $request): RedirectResponse
    {
        $this->authorize('update', HrEntitySetting::class);

        $validated = $request->validated();
        $this->settings->upsert((int) $validated['legal_entity_id'], [
            'default_holiday_calendar_id' => $validated['default_holiday_calendar_id'] ?? null,
            'employee_code_sequence_key' => $validated['employee_code_sequence_key'] ?? null,
            'settings_json' => $validated['settings_json'] ?? [],
        ]);

        return back()->with('success', __('HR Entity Settings Saved Successfully.'));
    }
}
