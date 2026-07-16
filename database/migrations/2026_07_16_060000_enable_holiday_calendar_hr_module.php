<?php

declare(strict_types=1);

use App\Models\BranchHrProfile;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        BranchHrProfile::query()->each(function (BranchHrProfile $profile): void {
            $modules = $profile->hr_enabled_modules;

            if (! is_array($modules) || $modules === []) {
                $profile->update([
                    'hr_enabled_modules' => ['expenses', 'hr', 'holiday_calendar'],
                ]);

                return;
            }

            if (! in_array('hr', $modules, true)) {
                return;
            }

            if (in_array('holiday_calendar', $modules, true)) {
                return;
            }

            $modules[] = 'holiday_calendar';
            $profile->update(['hr_enabled_modules' => array_values(array_unique($modules))]);
        });
    }

    public function down(): void
    {
        BranchHrProfile::query()->each(function (BranchHrProfile $profile): void {
            $modules = $profile->hr_enabled_modules;

            if (! is_array($modules)) {
                return;
            }

            $filtered = array_values(array_filter(
                $modules,
                fn (string $module): bool => $module !== 'holiday_calendar',
            ));

            $profile->update(['hr_enabled_modules' => $filtered]);
        });
    }
};
