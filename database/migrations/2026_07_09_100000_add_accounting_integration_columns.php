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
                if (! Schema::hasColumn('sale_items', 'cost_consumed')) {
                    $table->decimal('cost_consumed', 14, 2)->nullable()->after('line_total_inc_tax');
                }
                if (! Schema::hasColumn('sale_items', 'cogs_journal_entry_id')) {
                    $table->foreignId('cogs_journal_entry_id')
                        ->nullable()
                        ->after('cost_consumed')
                        ->constrained('journal_entries')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('petty_cash_registers')) {
            Schema::table('petty_cash_registers', function (Blueprint $table) {
                if (! Schema::hasColumn('petty_cash_registers', 'approval_threshold_amount')) {
                    $table->decimal('approval_threshold_amount', 14, 2)->default(0)->after('variance_tolerance_amount');
                }
            });
        }

        if (Schema::hasTable('petty_cash_vouchers')) {
            Schema::table('petty_cash_vouchers', function (Blueprint $table) {
                if (! Schema::hasColumn('petty_cash_vouchers', 'approval_required')) {
                    $table->boolean('approval_required')->default(false)->after('description');
                }
                if (! Schema::hasColumn('petty_cash_vouchers', 'approved_by_user_id')) {
                    $table->foreignId('approved_by_user_id')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('petty_cash_vouchers', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
                }
                if (! Schema::hasColumn('petty_cash_vouchers', 'rejection_reason')) {
                    $table->string('rejection_reason')->nullable()->after('approved_at');
                }
            });
        }

        if (Schema::hasTable('cheques')) {
            Schema::table('cheques', function (Blueprint $table) {
                if (! Schema::hasColumn('cheques', 'dishonour_charge_amount')) {
                    $table->decimal('dishonour_charge_amount', 14, 2)->nullable()->after('status');
                }
            });
        }

        if (Schema::hasTable('accounting_events')) {
            Schema::table('accounting_events', function (Blueprint $table) {
                try {
                    $table->dropUnique(['event_type', 'source_type', 'source_id']);
                } catch (Throwable) {
                    // Index may already be removed.
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                if (Schema::hasColumn('sale_items', 'cogs_journal_entry_id')) {
                    $table->dropConstrainedForeignId('cogs_journal_entry_id');
                }
                if (Schema::hasColumn('sale_items', 'cost_consumed')) {
                    $table->dropColumn('cost_consumed');
                }
            });
        }

        if (Schema::hasTable('petty_cash_registers')) {
            Schema::table('petty_cash_registers', function (Blueprint $table) {
                if (Schema::hasColumn('petty_cash_registers', 'approval_threshold_amount')) {
                    $table->dropColumn('approval_threshold_amount');
                }
            });
        }

        if (Schema::hasTable('petty_cash_vouchers')) {
            Schema::table('petty_cash_vouchers', function (Blueprint $table) {
                $columns = ['rejection_reason', 'approved_at', 'approved_by_user_id', 'approval_required'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('petty_cash_vouchers', $column)) {
                        if ($column === 'approved_by_user_id') {
                            $table->dropConstrainedForeignId('approved_by_user_id');
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('cheques')) {
            Schema::table('cheques', function (Blueprint $table) {
                if (Schema::hasColumn('cheques', 'dishonour_charge_amount')) {
                    $table->dropColumn('dishonour_charge_amount');
                }
            });
        }
    }
};
