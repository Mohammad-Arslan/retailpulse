<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const METHODS = [
        'cash',
        'card',
        'mobile_wallet',
        'bank_transfer',
        'credit',
        'wallet',
        'store_credit',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('sale_payments')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $values = implode("','", self::METHODS);

        DB::statement(
            "ALTER TABLE `sale_payments` MODIFY COLUMN `method` ENUM('{$values}') NOT NULL"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('sale_payments')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $legacy = ['cash', 'card', 'mobile_wallet', 'bank_transfer', 'credit'];
        $values = implode("','", $legacy);

        DB::statement(
            "ALTER TABLE `sale_payments` MODIFY COLUMN `method` ENUM('{$values}') NOT NULL"
        );
    }
};
