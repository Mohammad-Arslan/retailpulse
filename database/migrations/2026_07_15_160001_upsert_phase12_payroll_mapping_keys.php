<?php

declare(strict_types=1);

use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use Illuminate\Database\Migrations\Migration;

/**
 * Idempotent upsert of Phase 12 Block 6 account_mappings keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        $accounts = ChartOfAccount::query()
            ->whereIn('code', ['2210', '5200'])
            ->pluck('id', 'code');

        $mappings = [
            ['mapping_key' => 'statutory_payable', 'account_code' => '2210'],
            ['mapping_key' => 'hra_expense', 'account_code' => '5200'],
        ];

        foreach ($mappings as $mapping) {
            $accountId = $accounts->get($mapping['account_code']);

            if ($accountId === null) {
                continue;
            }

            AccountMapping::query()->firstOrCreate(
                [
                    'mapping_key' => $mapping['mapping_key'],
                    'payment_method' => null,
                ],
                [
                    'account_id' => $accountId,
                    'status' => 'active',
                    'priority' => 100,
                ],
            );
        }
    }

    public function down(): void
    {
        AccountMapping::query()
            ->whereIn('mapping_key', ['statutory_payable', 'hra_expense'])
            ->delete();
    }
};
