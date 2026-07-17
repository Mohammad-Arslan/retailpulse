<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_request_reschedules')) {
            Schema::create('leave_request_reschedules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
                $table->date('old_start_date');
                $table->date('old_end_date');
                $table->date('new_start_date');
                $table->date('new_end_date');
                $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->index(['leave_request_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_reschedules');
    }
};
