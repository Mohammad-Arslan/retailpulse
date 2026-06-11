<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'mysql') {
            $result = DB::select(
                "SELECT COUNT(*) AS cnt FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'pos_carts'
                   AND index_name = 'pos_carts_cashier_slot_active_unique'"
            );

            return (int) ($result[0]->cnt ?? 0) > 0;
        }

        return collect(Schema::getIndexes('pos_carts'))
            ->pluck('name')
            ->contains('pos_carts_cashier_slot_active_unique');
    }

    public function up(): void
    {
        if ($this->indexExists()) {
            Schema::table('pos_carts', function (Blueprint $table) {
                $table->dropUnique('pos_carts_cashier_slot_active_unique');
            });
        }
    }

    public function down(): void
    {
        if (! $this->indexExists()) {
            Schema::table('pos_carts', function (Blueprint $table) {
                $table->unique(['cashier_id', 'slot', 'status'], 'pos_carts_cashier_slot_active_unique');
            });
        }
    }
};
