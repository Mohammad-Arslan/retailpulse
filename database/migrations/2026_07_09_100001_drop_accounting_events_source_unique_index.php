<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $indexName = 'accounting_events_event_type_source_type_source_id_unique';

    private function indexExists(): bool
    {
        return collect(Schema::getIndexes('accounting_events'))
            ->pluck('name')
            ->contains($this->indexName);
    }

    public function up(): void
    {
        if (! Schema::hasTable('accounting_events')) {
            return;
        }

        if (! $this->indexExists()) {
            return;
        }

        Schema::table('accounting_events', function (Blueprint $table) {
            $table->dropUnique($this->indexName);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_events')) {
            return;
        }

        if ($this->indexExists()) {
            return;
        }

        Schema::table('accounting_events', function (Blueprint $table) {
            $table->unique(['event_type', 'source_type', 'source_id']);
        });
    }
};
