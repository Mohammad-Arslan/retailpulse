<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                if (! Schema::hasColumn('sale_items', 'cost_estimated')) {
                    $table->boolean('cost_estimated')->default(false)->after('cogs_journal_entry_id');
                }
                if (! Schema::hasColumn('sale_items', 'cost_basis')) {
                    $table->string('cost_basis', 32)->nullable()->after('cost_estimated');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                if (Schema::hasColumn('sale_items', 'cost_basis')) {
                    $table->dropColumn('cost_basis');
                }
                if (Schema::hasColumn('sale_items', 'cost_estimated')) {
                    $table->dropColumn('cost_estimated');
                }
            });
        }
    }
};
