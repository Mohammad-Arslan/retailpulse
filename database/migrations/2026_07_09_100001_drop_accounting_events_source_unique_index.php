<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_events')) {
            return;
        }

        Schema::table('accounting_events', function (Blueprint $table) {
            try {
                $table->dropUnique(['event_type', 'source_type', 'source_id']);
            } catch (Throwable) {
                // Index may already be removed.
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_events')) {
            return;
        }

        Schema::table('accounting_events', function (Blueprint $table) {
            $table->unique(['event_type', 'source_type', 'source_id']);
        });
    }
};
