<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSource;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\Contracts\AttendanceSourceProvider;
use App\Services\PosPinService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class AttendanceService
{
    /** @var array<string, AttendanceSourceProvider> */
    private array $providers;

    public function __construct(
        array $providers,
        private readonly PosPinService $posPinService,
    ) {
        $this->providers = $providers;
    }

    public function registerProvider(string $driverKey, AttendanceSourceProvider $provider): void
    {
        $this->providers[$driverKey] = $provider;
    }

    public function clockIn(AttendanceSource $source, AttendanceClockPayload $payload): AttendanceRecord
    {
        $this->assertSourceActive($source);

        return $this->resolveProvider($source->driver)->clockIn($source, $payload);
    }

    public function clockOut(AttendanceSource $source, AttendanceClockPayload $payload): AttendanceRecord
    {
        $this->assertSourceActive($source);

        return $this->resolveProvider($source->driver)->clockOut($source, $payload);
    }

    public function clockInViaPosPin(
        int $employeeId,
        int $branchId,
        string $pin,
        ?CarbonImmutable $at = null,
    ): AttendanceRecord {
        $employee = Employee::query()->with('user')->findOrFail($employeeId);
        $user = $employee->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'employee_id' => __('This employee has no linked user account for PIN verification.'),
            ]);
        }

        if (! $this->posPinService->verifyPin($user, $pin)) {
            throw ValidationException::withMessages([
                'pin' => __('Invalid PIN.'),
            ]);
        }

        $source = $this->resolveActiveSource('pos_pin', $branchId);

        return $this->clockIn($source, new AttendanceClockPayload(
            employeeId: $employeeId,
            branchId: $branchId,
            at: $at,
        ));
    }

    public function clockOutViaPosPin(
        int $employeeId,
        int $branchId,
        string $pin,
        ?CarbonImmutable $at = null,
        ?int $openRecordId = null,
    ): AttendanceRecord {
        $employee = Employee::query()->with('user')->findOrFail($employeeId);
        $user = $employee->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'employee_id' => __('This employee has no linked user account for PIN verification.'),
            ]);
        }

        if (! $this->posPinService->verifyPin($user, $pin)) {
            throw ValidationException::withMessages([
                'pin' => __('Invalid PIN.'),
            ]);
        }

        $source = $this->resolveActiveSource('pos_pin', $branchId);

        return $this->clockOut($source, new AttendanceClockPayload(
            employeeId: $employeeId,
            branchId: $branchId,
            at: $at,
            openRecordId: $openRecordId,
        ));
    }

    public function resolveActiveSource(string $driver, ?int $branchId = null): AttendanceSource
    {
        if ($branchId !== null) {
            $branchSource = AttendanceSource::query()
                ->where('driver', $driver)
                ->where('status', 'active')
                ->where('branch_id', $branchId)
                ->first();

            if ($branchSource !== null) {
                return $branchSource;
            }
        }

        $source = AttendanceSource::query()
            ->where('driver', $driver)
            ->where('status', 'active')
            ->whereNull('branch_id')
            ->first();

        if ($source === null) {
            throw ValidationException::withMessages([
                'source' => __('No active attendance source is configured for this driver.'),
            ]);
        }

        return $source;
    }

    private function resolveProvider(string $driverKey): AttendanceSourceProvider
    {
        if (! array_key_exists($driverKey, $this->providers)) {
            throw new UnsupportedAttendanceDriverException($driverKey);
        }

        return $this->providers[$driverKey];
    }

    private function assertSourceActive(AttendanceSource $source): void
    {
        if ($source->status !== 'active') {
            throw ValidationException::withMessages([
                'source' => __('This attendance source is not active.'),
            ]);
        }
    }
}
