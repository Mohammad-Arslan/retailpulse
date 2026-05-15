<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SUPER_ADMIN_PASSWORD');

        if (empty($password)) {
            $this->command?->warn('SUPER_ADMIN_PASSWORD is not set. Skipping Super Admin user seed.');

            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'admin@retailpulse.local')],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make($password),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles(['super-admin']);
    }
}
