<?php

declare(strict_types=1);

use App\Models\LoyaltyProgram;
use Database\Seeders\LoyaltyEngineSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (LoyaltyProgram::query()->exists()) {
            return;
        }

        (new LoyaltyEngineSeeder)->run();
    }

    public function down(): void
    {
        // Seeded reference data — no rollback
    }
};
