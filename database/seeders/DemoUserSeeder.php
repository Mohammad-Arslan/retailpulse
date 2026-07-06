<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use App\Services\PosPinService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DemoUserSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Password@123';

    private const DEMO_PIN = '123456';

    public function run(): void
    {
        $hq = Branch::query()->where('code', 'HQ')->first();
        $downtown = Branch::query()->where('code', 'DT01')->first();

        if ($hq === null || $downtown === null) {
            $this->command?->warn('DemoUserSeeder: branches HQ / DT01 not found — run BranchSeeder first.');

            return;
        }

        $pinService = app(PosPinService::class);

        $manager = User::query()->updateOrCreate(
            ['email' => 'manager@retailpulse.local'],
            [
                'name' => 'Branch Manager',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $manager->syncRoles(['branch-manager']);
        $manager->branches()->sync([
            $hq->id => ['is_primary' => true],
            $downtown->id => ['is_primary' => false],
        ]);
        $pinService->setPin($manager, self::DEMO_PIN);

        $cashier = User::query()->updateOrCreate(
            ['email' => 'cashier@retailpulse.local'],
            [
                'name' => 'POS Cashier',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $cashier->syncRoles(['cashier']);
        $cashier->branches()->sync([
            $downtown->id => ['is_primary' => true],
        ]);
        $pinService->setPin($cashier, self::DEMO_PIN);

        $accountant = User::query()->updateOrCreate(
            ['email' => 'accountant@retailpulse.local'],
            [
                'name' => 'Finance Accountant',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $accountant->syncRoles(['accountant']);
        $accountant->branches()->sync([
            $hq->id => ['is_primary' => true],
        ]);
    }
}
