<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_settings')) {
            Schema::table('financial_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('financial_settings', 'fiscal_year_reopen_window_hours')) {
                    $table->unsignedSmallInteger('fiscal_year_reopen_window_hours')->default(48)->after('accounting_cutover_date');
                }

                if (! Schema::hasColumn('financial_settings', 'default_sales_tax_type_id')) {
                    $table->foreignId('default_sales_tax_type_id')->nullable()->after('default_tax_type_id')->constrained('tax_types')->nullOnDelete();
                }

                if (! Schema::hasColumn('financial_settings', 'default_purchase_tax_type_id')) {
                    $table->foreignId('default_purchase_tax_type_id')->nullable()->after('default_sales_tax_type_id')->constrained('tax_types')->nullOnDelete();
                }

                if (! Schema::hasColumn('financial_settings', 'tax_reporting_enabled')) {
                    $table->boolean('tax_reporting_enabled')->default(true)->after('default_purchase_tax_type_id');
                }

                if (! Schema::hasColumn('financial_settings', 'tax_return_frequency')) {
                    $table->string('tax_return_frequency', 16)->default('monthly')->after('tax_reporting_enabled');
                }
            });
        }

        if (Schema::hasTable('fiscal_years')) {
            Schema::table('fiscal_years', function (Blueprint $table) {
                if (! Schema::hasColumn('fiscal_years', 'reopen_expires_at')) {
                    $table->timestamp('reopen_expires_at')->nullable()->after('reopened_by');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fiscal_years') && Schema::hasColumn('fiscal_years', 'reopen_expires_at')) {
            Schema::table('fiscal_years', function (Blueprint $table) {
                $table->dropColumn('reopen_expires_at');
            });
        }

        if (Schema::hasTable('financial_settings')) {
            Schema::table('financial_settings', function (Blueprint $table) {
                if (Schema::hasColumn('financial_settings', 'tax_return_frequency')) {
                    $table->dropColumn('tax_return_frequency');
                }

                if (Schema::hasColumn('financial_settings', 'tax_reporting_enabled')) {
                    $table->dropColumn('tax_reporting_enabled');
                }

                if (Schema::hasColumn('financial_settings', 'default_purchase_tax_type_id')) {
                    $table->dropConstrainedForeignId('default_purchase_tax_type_id');
                }

                if (Schema::hasColumn('financial_settings', 'default_sales_tax_type_id')) {
                    $table->dropConstrainedForeignId('default_sales_tax_type_id');
                }

                if (Schema::hasColumn('financial_settings', 'fiscal_year_reopen_window_hours')) {
                    $table->dropColumn('fiscal_year_reopen_window_hours');
                }
            });
        }
    }
};
