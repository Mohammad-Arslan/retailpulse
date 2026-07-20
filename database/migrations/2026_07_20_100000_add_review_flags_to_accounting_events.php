<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_events')) {
            Schema::table('accounting_events', function (Blueprint $table) {
                if (! Schema::hasColumn('accounting_events', 'flagged_for_review_at')) {
                    $table->timestamp('flagged_for_review_at')->nullable()->after('processed_at');
                }
                if (! Schema::hasColumn('accounting_events', 'flagged_for_review_reason')) {
                    $table->string('flagged_for_review_reason')->nullable()->after('flagged_for_review_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('accounting_events')) {
            Schema::table('accounting_events', function (Blueprint $table) {
                if (Schema::hasColumn('accounting_events', 'flagged_for_review_reason')) {
                    $table->dropColumn('flagged_for_review_reason');
                }
                if (Schema::hasColumn('accounting_events', 'flagged_for_review_at')) {
                    $table->dropColumn('flagged_for_review_at');
                }
            });
        }
    }
};
